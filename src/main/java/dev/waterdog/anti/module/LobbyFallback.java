package dev.waterdog.anti.module;

import dev.waterdog.anti.WaterdogAnti;
import dev.waterdog.waterdogpe.network.connection.handler.IReconnectHandler;
import dev.waterdog.waterdogpe.network.connection.handler.ReconnectReason;
import dev.waterdog.waterdogpe.network.serverinfo.ServerInfo;
import dev.waterdog.waterdogpe.player.ProxiedPlayer;

import java.util.List;

/**
 * Keeps players on the network when a downstream server dies.
 * <p>
 * Replaces the proxy's reconnect handler with a lobby-aware one: instead of letting a crash/kick/timeout
 * drop the player out of the network, they are moved to the healthiest lobby. This is the 2.x way of
 * doing what a "server down listener" would do on 1.x - the handler runs inside the failure path, so it
 * has to stay fast and must never block (no DNS, no database).
 */
public class LobbyFallback implements IReconnectHandler {

    private final WaterdogAnti plugin;
    private IReconnectHandler previous;
    private boolean installed;

    public LobbyFallback(WaterdogAnti plugin) {
        this.plugin = plugin;
    }

    public void enable() {
        if (!this.plugin.settings().bool("lobby-fallback.enabled", true)) {
            this.plugin.getLogger().info("LobbyFallback is disabled in the config.");
            return;
        }
        if (this.lobbies().isEmpty()) {
            this.plugin.getLogger().info("LobbyFallback disabled: lobby-fallback.lobby-servers is empty "
                    + "(the proxy's own reconnect handler stays in place).");
            return;
        }
        this.previous = this.plugin.getProxy().getReconnectHandler();
        this.plugin.getProxy().setReconnectHandler(this);
        this.installed = true;
        this.plugin.getLogger().info("LobbyFallback enabled with lobbies: " + String.join(", ", this.lobbies()));
    }

    public void disable() {
        if (this.installed && this.plugin.getProxy().getReconnectHandler() == this && this.previous != null) {
            this.plugin.getProxy().setReconnectHandler(this.previous);
        }
        this.installed = false;
    }

    public boolean isInstalled() {
        return this.installed;
    }

    @Override
    public ServerInfo getFallbackServer(ProxiedPlayer player, ServerInfo oldServer, ReconnectReason reason, String kickMessage) {
        String reasonName = reason == null ? "unknown" : reason.getName();
        String message = kickMessage == null ? "" : kickMessage;

        // A real punishment (ban, kick reason set by staff) must survive - never move those players to a lobby.
        for (String ignored : this.plugin.settings().list("lobby-fallback.ignore-kick-messages")) {
            if (!ignored.isBlank() && message.toLowerCase().contains(ignored.toLowerCase())) {
                return null;
            }
        }

        // Everything else: only fall back for the reasons the owner enabled, otherwise keep the old handler.
        boolean allowed = false;
        for (String configured : this.plugin.settings().list("lobby-fallback.fallback-on")) {
            if (configured.equalsIgnoreCase(reasonName)) {
                allowed = true;
                break;
            }
        }
        if (!allowed) {
            return this.previous == null ? null
                    : this.previous.getFallbackServer(player, oldServer, reason, message);
        }

        ServerInfo target = this.pick(player, oldServer);
        if (target == null) {
            player.sendMessage(this.plugin.settings().string("lobby-fallback.no-lobby-message",
                    "§cNo lobby available right now. Please rejoin in a few seconds."));
            return null;
        }

        this.plugin.getLogger().info("Fallback: " + player.getName() + " -> " + target.getServerName()
                + " (reason " + reasonName + ")");
        player.sendMessage(this.plugin.settings().string("lobby-fallback.transfer-message",
                "§eServer went down - sending you to the lobby..."));
        return target;
    }

    /**
     * A transfer that failed before the player ever left their current server is recoverable in place:
     * returning null keeps them where they are.
     */
    @Override
    public ServerInfo getTransferFailureServer(ProxiedPlayer player, ServerInfo targetServer, ReconnectReason reason, String kickMessage) {
        if (player.getServerInfo() != null) {
            return null;
        }
        return this.getFallbackServer(player, targetServer, reason, kickMessage);
    }

    private ServerInfo pick(ProxiedPlayer player, ServerInfo oldServer) {
        String strategy = this.plugin.settings().string("lobby-fallback.strategy", "least-players");
        ServerInfo best = null;
        int bestPlayers = Integer.MAX_VALUE;

        for (String name : this.lobbies()) {
            ServerInfo server = this.plugin.getProxy().getServerInfo(name);
            if (server == null) {
                continue;
            }
            if (oldServer != null && server.getServerName().equalsIgnoreCase(oldServer.getServerName())) {
                continue; // that is the server that just died
            }
            if (strategy.equalsIgnoreCase("first")) {
                return server;
            }
            int players = server.getPlayers().size();
            if (players < bestPlayers) {
                bestPlayers = players;
                best = server;
            }
        }
        return best;
    }

    private List<String> lobbies() {
        return this.plugin.settings().list("lobby-fallback.lobby-servers");
    }
}

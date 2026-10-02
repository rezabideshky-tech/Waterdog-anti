package com.arvangaming.wdlf;

import dev.waterdog.waterdogpe.ProxyServer;
import dev.waterdog.waterdogpe.network.connection.handler.DefaultReconnectHandler;
import dev.waterdog.waterdogpe.network.connection.handler.IReconnectHandler;
import dev.waterdog.waterdogpe.network.connection.handler.ReconnectReason;
import dev.waterdog.waterdogpe.network.serverinfo.ServerInfo;
import dev.waterdog.waterdogpe.player.ProxiedPlayer;

import java.util.HashSet;
import java.util.Set;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * WaterdogPE's supported fallback hook. It is called for backend kicks,
 * timeouts and connection failures, so no guessed disconnect event or delay is needed.
 */
final class LobbyReconnectHandler implements IReconnectHandler {
    private final WaterdogLobbyFallback plugin;
    private final FallbackConfig config;
    private final IReconnectHandler delegate;
    private final ConcurrentHashMap<UUID, AttemptState> attempts = new ConcurrentHashMap<UUID, AttemptState>();

    LobbyReconnectHandler(WaterdogLobbyFallback plugin, FallbackConfig config, IReconnectHandler delegate) {
        this.plugin = plugin;
        this.config = config;
        this.delegate = delegate == null ? new DefaultReconnectHandler() : delegate;
    }

    @Override
    public ServerInfo getFallbackServer(ProxiedPlayer player,
                                        ServerInfo oldServer,
                                        ReconnectReason reason,
                                        String kickMessage) {
        if (player == null || !this.config.shouldHandle(reasonName(reason)) || this.config.lobbyServers.isEmpty()) {
            return this.delegate.getFallbackServer(player, oldServer, reason, kickMessage);
        }

        AttemptState state = this.stateFor(player);
        ServerInfo current = player.getServerInfo();
        ServerInfo lobby = this.findNextLobby(player, state, oldServer, current);
        if (lobby != null) {
            this.notifyTransfer(player, state);
            this.plugin.getLogger().info("Sending player " + player.getName() + " to lobby '"
                    + lobby.getServerName() + "' after " + reasonName(reason) + " on '"
                    + serverName(oldServer) + "'.");
            return lobby;
        }

        if (!state.hasSelectedConfiguredLobby()) {
            // The configured names may not exist in Waterdog's servers section.
            // Preserve the proxy's original reconnect behavior in that case.
            this.plugin.getLogger().warn("No configured lobby name is registered on WaterdogPE; delegating fallback.");
            return this.delegate.getFallbackServer(player, oldServer, reason, kickMessage);
        }

        this.plugin.getLogger().warn("All configured lobbies were tried for " + player.getName() + "; disconnecting instead of looping.");
        this.sendIfPresent(player, this.config.noLobbyMessage);
        return null;
    }

    @Override
    public ServerInfo getTransferFailureServer(ProxiedPlayer player,
                                               ServerInfo targetServer,
                                               ReconnectReason reason,
                                               String kickMessage) {
        if (player == null || !this.config.fallbackOnTransferFailure
                || !this.config.shouldHandle(reasonName(reason)) || this.config.lobbyServers.isEmpty()) {
            return this.delegate.getTransferFailureServer(player, targetServer, reason, kickMessage);
        }

        ServerInfo current = player.getServerInfo();
        if (this.config.keepCurrentLobbyOnTransferFailure && current != null
                && this.config.isLobby(current.getServerName())) {
            this.sendIfPresent(player, this.config.currentLobbyMessage);
            return null; // Waterdog keeps the player on the still-connected lobby.
        }

        AttemptState state = this.stateFor(player);
        ServerInfo lobby = this.findNextLobby(player, state, targetServer, current);
        if (lobby != null) {
            this.notifyTransfer(player, state);
            this.plugin.getLogger().info("Transfer to '" + serverName(targetServer) + "' failed for "
                    + player.getName() + "; selecting lobby '" + lobby.getServerName() + "'.");
            return lobby;
        }

        if (!state.hasSelectedConfiguredLobby()) {
            this.plugin.getLogger().warn("No configured lobby name is registered on WaterdogPE; delegating transfer fallback.");
            return this.delegate.getTransferFailureServer(player, targetServer, reason, kickMessage);
        }

        // This failure is recoverable; returning null keeps the player on their current backend.
        this.sendIfPresent(player, this.config.noLobbyMessage);
        return null;
    }

    void onTransferComplete(ProxiedPlayer player) {
        if (player != null) this.attempts.remove(player.getUniqueId());
    }

    void onPlayerDisconnected(ProxiedPlayer player) {
        if (player != null) this.attempts.remove(player.getUniqueId());
    }

    void clear() {
        this.attempts.clear();
    }

    private AttemptState stateFor(ProxiedPlayer player) {
        return this.attempts.computeIfAbsent(player.getUniqueId(), ignored -> new AttemptState());
    }

    private ServerInfo findNextLobby(ProxiedPlayer player,
                                     AttemptState state,
                                     ServerInfo failedServer,
                                     ServerInfo currentServer) {
        synchronized (state) {
            if (failedServer != null) state.tried.add(normalize(failedServer.getServerName()));
            if (currentServer != null) state.tried.add(normalize(currentServer.getServerName()));

            for (String name : this.config.lobbyServers) {
                String key = normalize(name);
                if (!state.tried.add(key)) continue;

                ServerInfo lobby = ProxyServer.getInstance().getServerInfo(name);
                if (lobby == null) {
                    this.plugin.getLogger().warn("Lobby '" + name + "' is not configured in WaterdogPE; skipping it.");
                    continue;
                }

                state.markSelectedConfiguredLobby();
                return lobby;
            }
            return null;
        }
    }

    private void notifyTransfer(ProxiedPlayer player, AttemptState state) {
        if (state.noticeSent.compareAndSet(false, true)) {
            this.sendIfPresent(player, this.config.transferMessage);
        }
    }

    private void sendIfPresent(ProxiedPlayer player, String message) {
        if (player != null && message != null && !message.trim().isEmpty()) {
            player.sendMessage(message);
        }
    }

    private static String reasonName(ReconnectReason reason) {
        return reason == null ? "unknown" : reason.getName();
    }

    private static String serverName(ServerInfo server) {
        return server == null ? "unknown" : server.getServerName();
    }

    private static String normalize(String value) {
        return value == null ? "" : value.trim().toLowerCase(java.util.Locale.ROOT);
    }

    private static final class AttemptState {
        private final Set<String> tried = new HashSet<String>();
        private final AtomicBoolean noticeSent = new AtomicBoolean(false);
        private boolean selectedConfiguredLobby;

        synchronized void markSelectedConfiguredLobby() {
            this.selectedConfiguredLobby = true;
        }

        synchronized boolean hasSelectedConfiguredLobby() {
            return this.selectedConfiguredLobby;
        }
    }
}

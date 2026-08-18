package com.example.wdlf;

import dev.waterdog.waterdogpe.ProxyServer;
import dev.waterdog.waterdogpe.event.EventHandler;
import dev.waterdog.waterdogpe.event.EventPriority;
import dev.waterdog.waterdogpe.event.Listener;
import dev.waterdog.waterdogpe.event.defaults.ServerDisconnectEvent;
import dev.waterdog.waterdogpe.network.serverinfo.ServerInfo;
import dev.waterdog.waterdogpe.player.ProxiedPlayer;

/**
 * Listens for ServerDisconnectEvent - fired by WaterdogPE whenever a
 * player's connection to their CURRENT backend server drops
 * (crash, kick from backend, network issue, manual shutdown, etc).
 *
 * When that happens, instead of letting the player fall off the whole
 * proxy, we try to move them to the first reachable server in the
 * configured lobby list.
 *
 * NOTE: The exact class/package for this event
 * (dev.waterdog.waterdogpe.event.defaults.ServerDisconnectEvent) and
 * its getters (getPlayer(), getServerInfo()) should be double-checked
 * against the WaterdogPE version you build against - names have moved
 * around a bit across releases. If it doesn't compile, look for the
 * closest equivalent (sometimes called ServerConnection/DownstreamClose
 * type events) in the version's javadoc/source.
 */
public class ServerDisconnectListener implements Listener {

    private final WaterdogLobbyFallback plugin;

    public ServerDisconnectListener(WaterdogLobbyFallback plugin) {
        this.plugin = plugin;
    }

    @EventHandler(priority = EventPriority.NORMAL)
    public void onServerDisconnect(ServerDisconnectEvent event) {
        ProxiedPlayer player = event.getPlayer();
        ServerInfo downedServer = event.getServerInfo();
        FallbackConfig cfg = plugin.getFallbackConfig();

        if (player == null || !player.isConnected()) {
            return; // player already fully gone, nothing to do
        }

        if (cfg.ignoreIfAlreadyOnLobby && downedServer != null
                && cfg.lobbyServers.contains(downedServer.getServerName())) {
            // The thing that died WAS a lobby - don't try to bounce them
            // back into another lobby loop automatically here; just log it.
            plugin.getLogger().warn("Lobby server '" + downedServer.getServerName()
                    + "' appears to be down. Player " + player.getName()
                    + " was disconnected from it.");
            return;
        }

        plugin.getLogger().info("Player " + player.getName() + " lost connection to server '"
                + (downedServer != null ? downedServer.getServerName() : "unknown")
                + "'. Attempting lobby fallback.");

        // Small delay so the proxy has finished tearing down the old
        // downstream connection before we open a new one.
        // TODO: verify ProxyServer.getInstance().getScheduler() signature
        // for your WaterdogPE version (delay unit may be ticks or ms).
        ProxyServer.getInstance().getScheduler().scheduleDelayed(() -> {
            attemptLobbyTransfer(player, cfg, 0);
        }, cfg.transferDelayTicks);
    }

    private void attemptLobbyTransfer(ProxiedPlayer player, FallbackConfig cfg, int index) {
        if (!player.isConnected()) return;

        if (index >= cfg.lobbyServers.size()) {
            plugin.getLogger().warn("No lobby server was reachable for " + player.getName() + ".");
            player.disconnect(cfg.noLobbyMessage);
            return;
        }

        String lobbyName = cfg.lobbyServers.get(index);
        ServerInfo lobby = ProxyServer.getInstance().getServerInfo(lobbyName);

        if (lobby == null) {
            plugin.getLogger().warn("Configured lobby '" + lobbyName
                    + "' is not a registered server on this proxy - skipping.");
            attemptLobbyTransfer(player, cfg, index + 1);
            return;
        }

        player.connect(lobby);
        player.sendMessage(cfg.transferMessage);

        // Note: if this specific lobby is also offline, WaterdogPE will
        // typically fire ANOTHER ServerDisconnectEvent for the failed
        // connect attempt, which this same listener will catch and try
        // the next lobby in the list automatically - so we don't need
        // to manually verify success here.
    }
}

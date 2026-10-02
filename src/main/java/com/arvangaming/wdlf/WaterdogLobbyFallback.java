package com.arvangaming.wdlf;

import dev.waterdog.waterdogpe.ProxyServer;
import dev.waterdog.waterdogpe.event.defaults.PlayerDisconnectedEvent;
import dev.waterdog.waterdogpe.event.defaults.TransferCompleteEvent;
import dev.waterdog.waterdogpe.network.connection.handler.DefaultReconnectHandler;
import dev.waterdog.waterdogpe.network.connection.handler.IReconnectHandler;
import dev.waterdog.waterdogpe.plugin.Plugin;

/**
 * Sends players to a configured lobby when their active WaterdogPE backend
 * becomes unavailable, and tries the next lobby if a fallback also fails.
 */
public final class WaterdogLobbyFallback extends Plugin {
    private IReconnectHandler originalReconnectHandler;
    private LobbyReconnectHandler lobbyReconnectHandler;

    @Override
    public void onEnable() {
        FallbackConfig config = FallbackConfig.load(this.getConfig());
        ProxyServer proxy = this.getProxy();

        this.originalReconnectHandler = proxy.getReconnectHandler();
        this.lobbyReconnectHandler = new LobbyReconnectHandler(
                this, config, this.originalReconnectHandler);
        proxy.setReconnectHandler(this.lobbyReconnectHandler);

        proxy.getEventManager().subscribe(TransferCompleteEvent.class, event ->
                this.lobbyReconnectHandler.onTransferComplete(event.getPlayer()));
        proxy.getEventManager().subscribe(PlayerDisconnectedEvent.class, event ->
                this.lobbyReconnectHandler.onPlayerDisconnected(event.getPlayer()));

        if (config.lobbyServers.isEmpty()) {
            this.getLogger().warn("No lobby-servers are configured; the previous WaterdogPE reconnect handler will be used.");
        } else {
            this.getLogger().info("Lobby fallback enabled. Configured lobbies: " + String.join(", ", config.lobbyServers));
        }
    }

    @Override
    public void onDisable() {
        ProxyServer proxy = this.getProxy();
        if (proxy != null && proxy.getReconnectHandler() == this.lobbyReconnectHandler) {
            // WaterdogPE must always retain a reconnect handler, even when the original one was unset.
            proxy.setReconnectHandler(this.originalReconnectHandler != null
                    ? this.originalReconnectHandler
                    : new DefaultReconnectHandler());
        }
        if (this.lobbyReconnectHandler != null) {
            this.lobbyReconnectHandler.clear();
        }
    }
}

package com.example.wdlf;

import dev.waterdog.waterdogpe.ProxyServer;
import dev.waterdog.waterdogpe.plugin.Plugin;

public class WaterdogLobbyFallback extends Plugin {

    private static WaterdogLobbyFallback instance;
    private FallbackConfig config;

    public static WaterdogLobbyFallback getInstance() {
        return instance;
    }

    @Override
    public void onStartup() {
        instance = this;
        this.config = FallbackConfig.load(this);

        ProxyServer.getInstance().getEventManager()
                .subscribe(dev.waterdog.waterdogpe.event.defaults.ServerDisconnectEvent.class,
                        new ServerDisconnectListener(this));

        getLogger().info("WaterdogLobbyFallback enabled - lobby list: " + config.lobbyServers);
    }

    @Override
    public void onDisable() {
        getLogger().info("WaterdogLobbyFallback disabled.");
    }

    public FallbackConfig getConfig() {
        return config;
    }
}

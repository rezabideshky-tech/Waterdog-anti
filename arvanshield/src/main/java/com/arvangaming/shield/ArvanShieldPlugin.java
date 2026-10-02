package com.arvangaming.shield;

import dev.waterdog.waterdogpe.ProxyServer;
import dev.waterdog.waterdogpe.command.CommandMap;
import dev.waterdog.waterdogpe.event.defaults.InitialServerConnectedEvent;
import dev.waterdog.waterdogpe.event.defaults.PlayerAuthenticatedEvent;
import dev.waterdog.waterdogpe.plugin.Plugin;
import dev.waterdog.waterdogpe.scheduler.TaskHandler;

public final class ArvanShieldPlugin extends Plugin {
    private AntiBotEngine engine;
    private TaskHandler<?> cleanupTask;

    @Override
    public void onEnable() {
        this.loadConfig();
        AntiBotConfig config = AntiBotConfig.load(this.getConfig());
        ProxyServer proxy = this.getProxy();
        this.engine = new AntiBotEngine(this, config);

        proxy.getEventManager().subscribe(PlayerAuthenticatedEvent.class, this.engine::onAuthenticated);
        proxy.getEventManager().subscribe(InitialServerConnectedEvent.class, this.engine::onInitialServerConnected);
        this.cleanupTask = proxy.getScheduler().scheduleRepeating(this.engine::cleanup, 1_200, true);

        CommandMap commandMap = proxy.getCommandMap();
        if (!commandMap.registerCommand(new ArvanShieldCommand(this))) {
            this.getLogger().warn("Could not register /arvanshield; a command with that name is already registered.");
        }

        this.getLogger().info("ArvanShield enabled in " + config.initialMode.configName()
                + " mode. Early login checks are active; no client version is used as a bot signal.");
    }

    @Override
    public void onDisable() {
        if (this.cleanupTask != null) {
            this.cleanupTask.cancel();
            this.cleanupTask = null;
        }
        if (this.engine != null) {
            this.engine.shutdown();
        }
        if (this.getProxy() != null && this.getProxy().getCommandMap() != null) {
            this.getProxy().getCommandMap().unregisterCommand("arvanshield");
        }
    }

    AntiBotEngine getEngine() {
        return this.engine;
    }

    void reloadShieldConfiguration() {
        this.loadConfig();
        AntiBotConfig config = AntiBotConfig.load(this.getConfig());
        if (this.engine != null) {
            this.engine.reload(config);
        }
        this.getLogger().info("ArvanShield configuration reloaded; mode=" + config.initialMode.configName() + ".");
    }
}

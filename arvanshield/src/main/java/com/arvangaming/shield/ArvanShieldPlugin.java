package com.arvangaming.shield;

import dev.waterdog.waterdogpe.ProxyServer;
import dev.waterdog.waterdogpe.command.CommandMap;
import dev.waterdog.waterdogpe.event.defaults.InitialServerConnectedEvent;
import dev.waterdog.waterdogpe.event.defaults.PlayerAuthenticatedEvent;
import dev.waterdog.waterdogpe.network.NetworkMetrics;
import dev.waterdog.waterdogpe.plugin.Plugin;
import dev.waterdog.waterdogpe.scheduler.TaskHandler;

public final class ArvanShieldPlugin extends Plugin {
    private AntiBotEngine engine;
    private PacketMetricsBridge packetMetricsBridge;
    private TaskHandler<?> cleanupTask;
    private TaskHandler<?> trafficSampleTask;

    @Override
    public void onEnable() {
        this.loadConfig();
        AntiBotConfig config = AntiBotConfig.load(this.getConfig());
        ProxyServer proxy = this.getProxy();
        TrafficWindow trafficWindow = new TrafficWindow();
        NetworkMetrics previousMetrics = proxy.getNetworkMetrics();
        this.packetMetricsBridge = new PacketMetricsBridge(previousMetrics, trafficWindow);
        proxy.setNetworkMetrics(this.packetMetricsBridge);
        this.engine = new AntiBotEngine(this, config, trafficWindow);

        proxy.getEventManager().subscribe(PlayerAuthenticatedEvent.class, this.engine::onAuthenticated);
        proxy.getEventManager().subscribe(InitialServerConnectedEvent.class, this.engine::onInitialServerConnected);
        this.cleanupTask = proxy.getScheduler().scheduleRepeating(this.engine::cleanup, 1_200, true);
        this.trafficSampleTask = proxy.getScheduler().scheduleRepeating(this.engine::sampleTraffic, 20, true);

        CommandMap commandMap = proxy.getCommandMap();
        if (!commandMap.registerCommand(new ArvanShieldCommand(this))) {
            this.getLogger().warn("Could not register /arvanshield; a command with that name is already registered.");
        }

        this.getLogger().info("ArvanShield 1.1.0 enabled in " + config.initialMode.configName()
                + " mode. Early login checks and aggregate packet metrics are active.");
        this.getLogger().info("Individual raw-packet cancellation is not exposed by the public WaterdogPE plugin API.");
        if (config.shedNewLoginsOnHighLoad && config.attackInboundBytesPerSecond == 0L) {
            this.getLogger().warn("High-load login shedding is enabled but its byte-rate threshold is 0; automatic shedding remains disabled.");
        }
    }

    @Override
    public void onDisable() {
        if (this.cleanupTask != null) {
            this.cleanupTask.cancel();
            this.cleanupTask = null;
        }
        if (this.trafficSampleTask != null) {
            this.trafficSampleTask.cancel();
            this.trafficSampleTask = null;
        }
        if (this.packetMetricsBridge != null && this.getProxy() != null
                && this.getProxy().getNetworkMetrics() == this.packetMetricsBridge) {
            this.getProxy().setNetworkMetrics(this.packetMetricsBridge.previous());
            this.packetMetricsBridge = null;
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

    TrafficWindow.Snapshot getTrafficSnapshot() {
        return this.engine == null ? TrafficWindow.Snapshot.empty() : this.engine.getTrafficSnapshot();
    }

    boolean isNetworkMetricsAttached() {
        return this.packetMetricsBridge != null && this.getProxy() != null
                && this.getProxy().getNetworkMetrics() == this.packetMetricsBridge;
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

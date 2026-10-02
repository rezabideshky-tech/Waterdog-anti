package dev.waterdog.anti;

import dev.waterdog.anti.command.AntiCommand;
import dev.waterdog.anti.module.ChatGuard;
import dev.waterdog.anti.module.JoinGuard;
import dev.waterdog.anti.module.LobbyFallback;
import dev.waterdog.anti.module.PacketGuard;
import dev.waterdog.waterdogpe.plugin.Plugin;

/**
 * WaterdogAnti - proxy side protection for WaterdogPE networks (Bedrock 1.20 - 1.26.30+).
 *
 * Modules:
 * <ul>
 *     <li>{@link JoinGuard} - anti bot / name / XUID / protocol checks before a player ever enters the network</li>
 *     <li>{@link ChatGuard} - chat and command spam</li>
 *     <li>{@link PacketGuard} - per player packet rate limits (flood / crash exploit protection)</li>
 *     <li>{@link LobbyFallback} - sends players to a healthy lobby when a downstream server dies</li>
 * </ul>
 */
public class WaterdogAnti extends Plugin {

    private AntiConfig settings;
    private ViolationManager violations;
    private PlayerStore playerStore;
    private JoinGuard joinGuard;
    private ChatGuard chatGuard;
    private PacketGuard packetGuard;
    private LobbyFallback lobbyFallback;
    private AntiCommand command;

    @Override
    public void onEnable() {
        // Copies config.yml from the jar on the first start and loads it.
        this.saveResource("config.yml");
        this.loadConfig();
        this.settings = new AntiConfig(this.getConfig());

        this.violations = new ViolationManager(this);

        this.playerStore = new PlayerStore(this);
        if (this.settings.bool("player-store.enabled", true)) {
            this.playerStore.load();
        }

        this.joinGuard = new JoinGuard(this);
        this.joinGuard.enable();

        this.chatGuard = new ChatGuard(this);
        this.chatGuard.enable();

        this.packetGuard = new PacketGuard(this);
        this.packetGuard.enable();

        this.lobbyFallback = new LobbyFallback(this);
        this.lobbyFallback.enable();

        this.command = new AntiCommand(this);
        this.getProxy().getCommandMap().registerCommand(this.command);

        // Single 1-second heartbeat for decay, cleanup and periodic disk writes.
        this.getProxy().getScheduler().scheduleRepeating(this::tick, 20);

        this.getLogger().info("WaterdogAnti v" + this.getDescription().version + " enabled - "
                + "join: " + this.settings.bool("join-guard.enabled", true)
                + ", chat: " + this.settings.bool("chat-guard.enabled", true)
                + ", packets: " + this.settings.bool("packet-guard.enabled", true)
                + ", fallback: " + this.lobbyFallback.isInstalled());
    }

    @Override
    public void onDisable() {
        try {
            this.getProxy().getCommandMap().unregisterCommand("waterdoganti");
        } catch (Throwable t) {
            this.getLogger().warn("Could not unregister the /wda command: " + t.getMessage());
        }
        if (this.lobbyFallback != null) {
            this.lobbyFallback.disable();
        }
        if (this.playerStore != null) {
            this.playerStore.save();
        }
        this.getLogger().info("WaterdogAnti disabled.");
    }

    private void tick() {
        long now = System.currentTimeMillis();
        this.joinGuard.tick(now);
        this.packetGuard.tick(now);
        this.violations.tick(now);
        this.playerStore.saveIfDirty();
    }

    /**
     * Re-reads config.yml. Module toggles (enabled: false) are applied on the next proxy start,
     * all numbers, lists and messages are live immediately.
     */
    public void reload() {
        this.loadConfig();
        this.settings = new AntiConfig(this.getConfig());
        this.packetGuard.reload();
        this.getLogger().info("Configuration reloaded.");
    }

    public AntiConfig settings() {
        return this.settings;
    }

    public ViolationManager violations() {
        return this.violations;
    }

    public PlayerStore playerStore() {
        return this.playerStore;
    }

    public JoinGuard joinGuard() {
        return this.joinGuard;
    }

    public ChatGuard chatGuard() {
        return this.chatGuard;
    }

    public PacketGuard packetGuard() {
        return this.packetGuard;
    }

    public LobbyFallback lobbyFallback() {
        return this.lobbyFallback;
    }
}

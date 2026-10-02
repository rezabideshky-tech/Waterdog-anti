package dev.waterdog.anti.module;

import dev.waterdog.anti.WaterdogAnti;
import dev.waterdog.waterdogpe.event.EventManager;
import dev.waterdog.waterdogpe.event.defaults.PlayerDisconnectedEvent;
import dev.waterdog.waterdogpe.event.defaults.PlayerLoginEvent;
import dev.waterdog.waterdogpe.network.protocol.Signals;
import dev.waterdog.waterdogpe.network.protocol.handler.PluginPacketHandler;
import dev.waterdog.waterdogpe.player.ProxiedPlayer;
import org.cloudburstmc.protocol.bedrock.PacketDirection;
import org.cloudburstmc.protocol.bedrock.packet.BedrockPacket;
import org.cloudburstmc.protocol.common.PacketSignal;

import java.net.InetSocketAddress;
import java.util.HashMap;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.TimeUnit;

/**
 * Per-player packet rate limiter.
 * <p>
 * WaterdogPE lets plugins inspect every packet that flows through a player session
 * ({@link PluginPacketHandler}); this module uses that hook to stop the classic Bedrock abuse patterns
 * (packet flood / crash exploits / sub-chunk request bombs) directly on the proxy, without touching
 * any downstream server. Over-limit packets are dropped before they ever reach the backend.
 */
public class PacketGuard {

    private final WaterdogAnti plugin;
    private final Map<UUID, PlayerStats> stats = new ConcurrentHashMap<>();

    private Map<String, Integer> limits;
    private int defaultLimit;
    private int windowSeconds;
    private double cancelFactor;
    private boolean enabled;

    public PacketGuard(WaterdogAnti plugin) {
        this.plugin = plugin;
        this.readSettings();
    }

    private void readSettings() {
        this.limits = this.plugin.settings().intMap("packet-guard.limits");
        this.defaultLimit = this.plugin.settings().integer("packet-guard.default-limit", 250);
        this.windowSeconds = Math.max(1, this.plugin.settings().integer("packet-guard.window-seconds", 1));
        this.cancelFactor = Math.max(1.0D, parseDouble(this.plugin.settings()
                .string("packet-guard.cancel-above-factor", "2.0"), 2.0D));
        this.enabled = this.plugin.settings().bool("packet-guard.enabled", true);
    }

    /**
     * Re-reads all cached limits after a {@code /wda reload}.
     */
    public void reload() {
        this.readSettings();
        this.plugin.getLogger().info("PacketGuard limits reloaded (" + this.limits.size() + " custom limits).");
    }

    public void enable() {
        if (!this.enabled) {
            this.plugin.getLogger().info("PacketGuard is disabled in the config.");
            return;
        }
        EventManager events = this.plugin.getProxy().getEventManager();
        // PlayerLoginEvent is fired (and awaited) before the very first downstream connection is made,
        // which is exactly the moment a plugin packet handler has to be registered.
        events.subscribe(PlayerLoginEvent.class, this::onLogin);
        events.subscribe(PlayerDisconnectedEvent.class, this::onDisconnect);
        this.plugin.getLogger().info("PacketGuard enabled (" + this.limits.size() + " custom limits, default "
                + this.defaultLimit + "/" + this.windowSeconds + "s).");
    }

    private void onLogin(PlayerLoginEvent event) {
        ProxiedPlayer player = event.getPlayer();
        if (player == null || this.hasHandler(player)) {
            return;
        }
        player.getPluginPacketHandlers().add(new PlayerHandler(player));
        this.stats.computeIfAbsent(player.getUniqueId(), id -> new PlayerStats());
    }

    private void onDisconnect(PlayerDisconnectedEvent event) {
        ProxiedPlayer player = event.getPlayer();
        if (player != null) {
            this.stats.remove(player.getUniqueId());
        }
    }

    private boolean hasHandler(ProxiedPlayer player) {
        for (PluginPacketHandler handler : player.getPluginPacketHandlers()) {
            if (handler instanceof PlayerHandler) {
                return true;
            }
        }
        return false;
    }

    /**
     * Called by the per-player handler for every server bound packet.
     */
    private PacketSignal handle(ProxiedPlayer player, BedrockPacket packet) {
        if (!this.enabled || !player.isConnected()) {
            return PacketSignal.UNHANDLED;
        }

        String packetName = packet.getClass().getSimpleName();
        Integer configured = this.limits.get(packetName);
        // Everything that is not configured shares one bucket, so tracking stays O(1) per player.
        String bucket = configured != null ? packetName : "*";
        int limit = configured != null ? configured : this.defaultLimit;
        if (limit <= 0) {
            return PacketSignal.UNHANDLED; // unlimited
        }

        long now = System.currentTimeMillis();
        PlayerStats playerStats = this.stats.computeIfAbsent(player.getUniqueId(), id -> new PlayerStats());
        int count = playerStats.increment(bucket, now, this.windowSeconds * 1000L, this.limits.size() + 2);

        if (count <= limit) {
            return PacketSignal.UNHANDLED;
        }

        if (count == limit + 1) {
            // Report only the first offending packet of every window, otherwise the alert itself becomes the flood.
            int points = this.plugin.violations().flag(player, "PacketGuard",
                    packetName + " x" + count + "/" + this.windowSeconds + "s (limit " + limit + ")", 2);
            this.evaluate(player, points);
        }

        // \u00abHANDLED\u00bb would make the proxy re-encode the packet, only \u00abCANCEL\u00bb drops it and
        // \u00abUNHANDLED\u00bb forwards the original buffer - which is what we want for everything we keep.
        if (count > limit * this.cancelFactor) {
            return Signals.CANCEL;
        }
        return PacketSignal.UNHANDLED;
    }

    private void evaluate(ProxiedPlayer player, int points) {
        int threshold = this.plugin.settings().integer("packet-guard.violations-before-action", 10);
        if (threshold <= 0 || points < threshold) {
            return;
        }
        String action = this.plugin.settings().string("packet-guard.action", "alert").toLowerCase();
        if (action.equals("kick") || action.equals("block")) {
            String message = this.plugin.settings().string("packet-guard.kick-message", "§cKicked: packet flood.");
            if (action.equals("block")) {
                InetSocketAddress address = player.getAddress();
                int seconds = this.plugin.settings().integer("packet-guard.block-duration-seconds", 600);
                if (address != null && address.getAddress() != null && seconds > 0) {
                    this.plugin.getProxy().getSecurityManager().blockAddress(address.getAddress(), seconds, TimeUnit.SECONDS);
                }
            }
            this.plugin.violations().alert("PacketGuard", player.getName() + " exceeded the packet limits (" + action + ")");
            player.disconnect(message);
        }
    }

    /**
     * Cleans up records of players that are no longer online. Called once per second.
     */
    public void tick(long now) {
        this.stats.keySet().removeIf(uuid -> !this.plugin.getProxy().getPlayers().containsKey(uuid));
    }

    public int trackedPlayers() {
        return this.stats.size();
    }

    private static double parseDouble(String value, double fallback) {
        try {
            return Double.parseDouble(value.trim());
        } catch (Exception e) {
            return fallback;
        }
    }

    private final class PlayerHandler implements PluginPacketHandler {
        private final ProxiedPlayer player;

        private PlayerHandler(ProxiedPlayer player) {
            this.player = player;
        }

        @Override
        public PacketSignal handlePacket(BedrockPacket packet, PacketDirection direction) {
            if (direction != PacketDirection.SERVER_BOUND) {
                return PacketSignal.UNHANDLED; // client bound traffic is produced by the servers
            }
            return PacketGuard.this.handle(this.player, packet);
        }
    }

    private static final class PlayerStats {
        private final Map<String, int[]> windows = new HashMap<>(); // bucket -> [windowId, count]

        synchronized int increment(String bucket, long now, long windowMs, int maxBuckets) {
            int[] window = this.windows.get(bucket);
            if (window == null) {
                if (this.windows.size() >= maxBuckets) {
                    this.windows.clear(); // safety valve, should never happen thanks to the "*" bucket
                }
                window = new int[]{-1, 0};
                this.windows.put(bucket, window);
            }
            int windowId = (int) (now / windowMs);
            if (window[0] != windowId) {
                window[0] = windowId;
                window[1] = 0;
            }
            return ++window[1];
        }
    }
}

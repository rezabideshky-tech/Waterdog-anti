package dev.waterdog.anti;

import dev.waterdog.waterdogpe.player.ProxiedPlayer;

import java.io.File;
import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardOpenOption;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.Map;
import java.util.Set;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

/**
 * Central violation bookkeeping.
 * <p>
 * Modules call {@link #flag(ProxiedPlayer, String, String, int)} with a weight; points decay over time
 * so a single burst of lag does not punish a player forever. Alerts are throttled per player/module and
 * delivered to the console, to an optional log file and to every online staff member holding the
 * configured permission.
 */
public class ViolationManager {

    private static final DateTimeFormatter TIME_FORMAT = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss");

    private final WaterdogAnti plugin;
    private final Path logFile;
    private final Map<UUID, Offense> offenses = new ConcurrentHashMap<>();
    private final Set<UUID> alertsDisabled = ConcurrentHashMap.newKeySet();
    private final Map<String, Long> lastAlert = new ConcurrentHashMap<>();

    public ViolationManager(WaterdogAnti plugin) {
        this.plugin = plugin;
        this.logFile = new File(plugin.getDataFolder(), "alerts.log").toPath();
    }

    /**
     * Adds violation points for a player and emits an alert.
     *
     * @return the total (decayed) amount of points the player now has
     */
    public int flag(ProxiedPlayer player, String module, String detail, int points) {
        if (player == null) {
            return 0;
        }
        long now = System.currentTimeMillis();
        Offense offense = this.offenses.computeIfAbsent(player.getUniqueId(), id -> new Offense());
        int total = offense.add(points, now);
        this.alert(module, detail + " §8(" + player.getName() + " §7points: " + total + "§8)", player.getUniqueId(),
                module + ":" + player.getUniqueId());
        return total;
    }

    /**
     * Emits a standalone alert (no violation points attached).
     */
    public void alert(String module, String message) {
        this.alert(module, message, null, module + ":" + message);
    }

    private void alert(String module, String message, UUID playerId, String throttleKey) {
        if (!this.plugin.settings().bool("alerts.enabled", true)) {
            return;
        }

        long now = System.currentTimeMillis();
        int throttle = Math.max(0, this.plugin.settings().integer("alerts.throttle-seconds", 5)) * 1000;
        Long previous = this.lastAlert.get(throttleKey);
        if (previous != null && now - previous < throttle) {
            return; // too spammy, skip
        }
        this.lastAlert.put(throttleKey, now);

        String line = "[" + module + "] " + this.stripColor(message);
        this.plugin.getLogger().warn(line);
        if (this.plugin.settings().bool("alerts.log-to-file", true)) {
            this.appendToFile(line);
        }

        String prefix = this.plugin.settings().string("alerts.prefix", "§8[§cAnti§8]§r ");
        String permission = this.plugin.settings().string("alerts.permission", "waterdoganti.alerts");
        for (ProxiedPlayer staff : this.plugin.getProxy().getPlayers().values()) {
            if (staff == null || staff.getUniqueId().equals(playerId) || !staff.isConnected()) {
                continue;
            }
            if (this.alertsDisabled.contains(staff.getUniqueId()) || !staff.hasPermission(permission)) {
                continue;
            }
            staff.sendMessage(prefix + "§7" + message);
        }
    }

    private void appendToFile(String line) {
        String timestamped = LocalDateTime.now().format(TIME_FORMAT) + " " + line + System.lineSeparator();
        try {
            Files.writeString(this.logFile, timestamped, StandardCharsets.UTF_8,
                    StandardOpenOption.CREATE, StandardOpenOption.APPEND);
        } catch (IOException e) {
            this.plugin.getLogger().error("Could not write to alerts.log", e);
        }
    }

    private String stripColor(String input) {
        return input == null ? "" : input.replaceAll("[§&][0-9a-fk-orA-FK-OR]", "");
    }

    public int points(UUID uuid) {
        Offense offense = this.offenses.get(uuid);
        return offense == null ? 0 : offense.total;
    }

    public void reset(UUID uuid) {
        this.offenses.remove(uuid);
    }

    public boolean alertsEnabled(UUID uuid) {
        return !this.alertsDisabled.contains(uuid);
    }

    public void toggleAlerts(UUID uuid) {
        if (!this.alertsDisabled.remove(uuid)) {
            this.alertsDisabled.add(uuid);
        }
    }

    /**
     * Decays points and drops records of players that are long gone. Called once per second.
     */
    public void tick(long now) {
        this.offenses.entrySet().removeIf(entry -> {
            Offense offense = entry.getValue();
            offense.decay(now);
            return offense.total <= 0 && now - offense.lastUpdate > 300_000L;
        });
    }

    private static final class Offense {
        private static final long DECAY_INTERVAL_MS = 15_000L;

        private volatile int total;
        private long lastDecay = System.currentTimeMillis();
        private long lastUpdate = System.currentTimeMillis();

        synchronized int add(int points, long now) {
            this.decay(now);
            this.total += points;
            this.lastUpdate = now;
            return this.total;
        }

        synchronized void decay(long now) {
            this.lastUpdate = now;
            long elapsed = now - this.lastDecay;
            if (elapsed < DECAY_INTERVAL_MS) {
                return;
            }
            int steps = (int) (elapsed / DECAY_INTERVAL_MS);
            this.lastDecay += (long) steps * DECAY_INTERVAL_MS;
            this.total = Math.max(0, this.total - steps);
        }
    }
}

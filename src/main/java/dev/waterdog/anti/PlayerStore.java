package dev.waterdog.anti;

import java.io.File;
import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * Tiny flat-file database that remembers which Minecraft name belongs to which XUID.
 * <p>
 * Bedrock (unlike Java) has a stable XUID per Xbox account, which makes name/XUID binding the most
 * reliable way to detect name spoofing: "Notch" today, "Admin" tomorrow with the same XUID.
 * File format is one record per line: {@code xuid<TAB>name<TAB>firstSeen<TAB>lastSeen}.
 */
public class PlayerStore {

    private final WaterdogAnti plugin;
    private final File file;
    private final Map<String, Entry> byXuid = new ConcurrentHashMap<>();
    private final Map<String, String> xuidByName = new ConcurrentHashMap<>();
    private final AtomicBoolean dirty = new AtomicBoolean(false);

    private volatile long lastSave = System.currentTimeMillis();

    public PlayerStore(WaterdogAnti plugin) {
        this.plugin = plugin;
        this.file = new File(plugin.getDataFolder(), "players.db");
    }

    public void load() {
        if (!this.file.exists()) {
            return;
        }
        try {
            List<String> lines = Files.readAllLines(this.file.toPath(), StandardCharsets.UTF_8);
            for (String line : lines) {
                if (line.isBlank() || line.startsWith("#")) {
                    continue;
                }
                String[] parts = line.split("\t");
                if (parts.length < 2) {
                    continue;
                }
                long first = parts.length > 2 ? parseLong(parts[2]) : 0L;
                long last = parts.length > 3 ? parseLong(parts[3]) : 0L;
                this.put(parts[0], parts[1], first, last);
            }
            this.plugin.getLogger().info("Loaded " + this.byXuid.size() + " player records.");
        } catch (IOException e) {
            this.plugin.getLogger().error("Could not read players.db", e);
        }
    }

    /**
     * @return the name previously bound to this XUID, or null when the account is new/unchanged.
     */
    public String record(String xuid, String name) {
        if (xuid == null || xuid.isEmpty() || name == null || name.isEmpty()) {
            return null;
        }
        long now = System.currentTimeMillis();
        Entry existing = this.byXuid.get(xuid);
        if (existing == null) {
            this.put(xuid, name, now, now);
            this.dirty.set(true);
            return null;
        }
        if (existing.name.equals(name)) {
            existing.lastSeen = now;
            return null;
        }
        String previous = existing.name;
        this.xuidByName.remove(previous.toLowerCase());
        this.put(xuid, name, existing.firstSeen, now);
        this.dirty.set(true);
        return previous;
    }

    /**
     * @return the XUID that this name was first seen with, or null when unknown.
     */
    public String xuidOf(String name) {
        return name == null ? null : this.xuidByName.get(name.toLowerCase());
    }

    /**
     * @return the last known name of an XUID, or null when unknown.
     */
    public String nameOf(String xuid) {
        Entry entry = this.byXuid.get(xuid);
        return entry == null ? null : entry.name;
    }

    public int size() {
        return this.byXuid.size();
    }

    private void put(String xuid, String name, long first, long last) {
        this.byXuid.put(xuid, new Entry(name, first, last));
        this.xuidByName.put(name.toLowerCase(), xuid);
    }

    private long parseLong(String value) {
        try {
            return Long.parseLong(value.trim());
        } catch (NumberFormatException e) {
            return 0L;
        }
    }

    /**
     * Called every second by the plugin; only touches the disk every 60 seconds when something changed.
     */
    public void saveIfDirty() {
        if (!this.dirty.get() || System.currentTimeMillis() - this.lastSave < 60_000L) {
            return;
        }
        this.save();
    }

    public synchronized void save() {
        this.dirty.set(false);
        this.lastSave = System.currentTimeMillis();
        List<String> lines = new ArrayList<>(this.byXuid.size() + 1);
        lines.add("# xuid\tname\tfirstSeen\tlastSeen");
        for (Map.Entry<String, Entry> entry : this.byXuid.entrySet()) {
            Entry value = entry.getValue();
            lines.add(entry.getKey() + "\t" + value.name + "\t" + value.firstSeen + "\t" + value.lastSeen);
        }
        try {
            Files.write(this.file.toPath(), lines, StandardCharsets.UTF_8);
        } catch (IOException e) {
            this.plugin.getLogger().error("Could not save players.db", e);
        }
    }

    private static final class Entry {
        private final String name;
        private final long firstSeen;
        private volatile long lastSeen;

        private Entry(String name, long firstSeen, long lastSeen) {
            this.name = name;
            this.firstSeen = firstSeen;
            this.lastSeen = lastSeen;
        }
    }
}

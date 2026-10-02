package dev.waterdog.anti.module;

import dev.waterdog.anti.WaterdogAnti;
import dev.waterdog.waterdogpe.command.CommandSender;
import dev.waterdog.waterdogpe.event.EventManager;
import dev.waterdog.waterdogpe.event.EventPriority;
import dev.waterdog.waterdogpe.event.defaults.DispatchCommandEvent;
import dev.waterdog.waterdogpe.event.defaults.PlayerChatEvent;
import dev.waterdog.waterdogpe.player.ProxiedPlayer;

import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;
import java.util.regex.Pattern;

/**
 * Chat & command spam protection.
 * <p>
 * Runs on {@link PlayerChatEvent} and {@link DispatchCommandEvent} so it also covers messages that the
 * downstream server would normally receive - the proxy is the only place where every player of the
 * network passes, which makes it the ideal spot for global chat rules.
 */
public class ChatGuard {

    private static final Pattern URL_PATTERN = Pattern.compile(
            "(?i)(https?://|www\\.|[a-z0-9-]+\\.(com|net|org|ir|xyz|top|fun|shop|store|tk|ml|ga|cf|gq)\\b)");
    private static final Pattern IP_PATTERN = Pattern.compile(
            "\\b((25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)\\.){3}(25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)\\b(:\\d{1,5})?");

    private final WaterdogAnti plugin;
    private final Map<UUID, ChatRecord> records = new ConcurrentHashMap<>();
    private final Map<UUID, Long> lastNotify = new ConcurrentHashMap<>();

    public ChatGuard(WaterdogAnti plugin) {
        this.plugin = plugin;
    }

    public void enable() {
        EventManager events = this.plugin.getProxy().getEventManager();
        events.subscribe(PlayerChatEvent.class, this::onChat, EventPriority.LOW);
        events.subscribe(DispatchCommandEvent.class, this::onCommand, EventPriority.LOW);
        this.plugin.getLogger().info("ChatGuard enabled.");
    }

    private void onChat(PlayerChatEvent event) {
        if (!this.plugin.settings().bool("chat-guard.enabled", true)) {
            return;
        }
        ProxiedPlayer player = event.getPlayer();
        if (player == null || this.isBypassed(player)) {
            return;
        }

        String message = event.getMessage() == null ? "" : event.getMessage();
        ChatRecord record = this.records.computeIfAbsent(player.getUniqueId(), id -> new ChatRecord());
        long now = System.currentTimeMillis();

        if (record.isMuted(now)) {
            event.setCancelled(true);
            this.notify(player, this.setting("chat-guard.too-fast-message", "§cYou are muted."));
            return;
        }

        int maxLength = this.setting("chat-guard.max-length", 120);
        if (maxLength > 0 && message.length() > maxLength) {
            if (message.length() > maxLength * 2) {
                event.setCancelled(true);
                this.punish(player, record, "chat length " + message.length());
                return;
            }
            message = message.substring(0, maxLength);
            event.setMessage(message);
        }

        boolean blocked = false;
        if (this.setting("chat-guard.block-repeated", true) && record.isRepeated(message)) {
            blocked = true;
        }
        for (String word : this.plugin.settings().list("chat-guard.blocked-words")) {
            if (!word.isBlank() && message.toLowerCase().contains(word.toLowerCase())) {
                blocked = true;
                break;
            }
        }
        if (!blocked && this.setting("chat-guard.block-urls", false) && URL_PATTERN.matcher(message).find()) {
            blocked = true;
        }
        if (!blocked && this.setting("chat-guard.block-ip-addresses", false) && IP_PATTERN.matcher(message).find()) {
            blocked = true;
        }

        if (blocked) {
            event.setCancelled(true);
            this.notify(player, this.setting("chat-guard.blocked-message", "§cThat message is not allowed."));
            this.punish(player, record, "blocked chat content");
            return;
        }

        // Rate limit last, so legitimate messages are passed through with as little work as possible.
        int windowSeconds = this.setting("chat-guard.window-seconds", 10);
        int maxMessages = this.setting("chat-guard.max-messages", 6);
        int minInterval = this.setting("chat-guard.min-interval-ms", 250);
        if (record.tooFast(now, windowSeconds, maxMessages, minInterval)) {
            event.setCancelled(true);
            this.notify(player, this.setting("chat-guard.too-fast-message", "§cYou are sending messages too fast!"));
            this.punish(player, record, "chat spam");
            return;
        }
        record.push(now, message);
    }

    private void onCommand(DispatchCommandEvent event) {
        if (!this.plugin.settings().bool("chat-guard.enabled", true)) {
            return;
        }
        CommandSender sender = event.getSender();
        if (!(sender instanceof ProxiedPlayer player) || this.isBypassed(player)) {
            return;
        }
        int windowSeconds = this.setting("chat-guard.window-seconds", 10);
        int limit = this.setting("chat-guard.commands-per-window", 12);
        ChatRecord record = this.records.computeIfAbsent(player.getUniqueId(), id -> new ChatRecord());
        if (record.tooManyCommands(System.currentTimeMillis(), windowSeconds, limit)) {
            event.setCancelled(true);
            event.setConsumeState(DispatchCommandEvent.ConsumeState.CONSUME);
            this.notify(player, this.setting("chat-guard.too-fast-message", "§cSlow down!"));
            this.punish(player, record, "command spam");
        }
    }

    private void punish(ProxiedPlayer player, ChatRecord record, String reason) {
        int violations = record.addViolation(System.currentTimeMillis());
        int total = this.plugin.violations().flag(player, "ChatGuard", reason, 2);
        int autoMute = this.setting("chat-guard.auto-mute-violations", 4);
        if (autoMute > 0 && violations >= autoMute) {
            record.mute(System.currentTimeMillis(), this.setting("chat-guard.window-seconds", 10) * 1000L);
            record.resetViolations();
        }
        if (total >= this.setting("chat-guard.kick-at-points", 40)) {
            player.disconnect(this.setting("chat-guard.kick-message", "§cKicked: chat abuse."));
        }
    }

    private void notify(ProxiedPlayer player, String message) {
        // Never answer faster than once per second per player: a spammer must not be able to turn our
        // own feedback messages into a lag machine.
        long now = System.currentTimeMillis();
        Long previous = this.lastNotify.get(player.getUniqueId());
        if (previous != null && now - previous < 1000L) {
            return;
        }
        this.lastNotify.put(player.getUniqueId(), now);
        player.sendMessage(message);
    }

    private boolean isBypassed(ProxiedPlayer player) {
        String permission = this.setting("general.bypass-permission", "waterdoganti.bypass");
        if (!permission.isBlank() && player.hasPermission(permission)) {
            return true;
        }
        for (String name : this.plugin.settings().list("general.bypass-names")) {
            if (name.equalsIgnoreCase(player.getName())) {
                return true;
            }
        }
        return false;
    }

    private String setting(String key, String fallback) {
        return this.plugin.settings().string(key, fallback);
    }

    private boolean setting(String key, boolean fallback) {
        return this.plugin.settings().bool(key, fallback);
    }

    private int setting(String key, int fallback) {
        return this.plugin.settings().integer(key, fallback);
    }

    private static final class ChatRecord {
        private final Map<Long, Integer> messageTimestamps = new ConcurrentHashMap<>();
        private final Map<Long, Integer> commandTimestamps = new ConcurrentHashMap<>();

        private String lastMessage = "";
        private long lastMessageAt;
        private long lastViolationAt;
        private int violations;
        private volatile long mutedUntil;

        synchronized boolean tooFast(long now, int windowSeconds, int maxMessages, int minIntervalMs) {
            if (minIntervalMs > 0 && now - this.lastMessageAt < minIntervalMs) {
                return true;
            }
            this.prune(this.messageTimestamps, now, windowSeconds * 1000L);
            int count = this.messageTimestamps.values().stream().mapToInt(Integer::intValue).sum();
            return maxMessages > 0 && count >= maxMessages;
        }

        synchronized boolean tooManyCommands(long now, int windowSeconds, int maxCommands) {
            this.prune(this.commandTimestamps, now, windowSeconds * 1000L);
            int count = this.commandTimestamps.values().stream().mapToInt(Integer::intValue).sum();
            if (maxCommands > 0 && count >= maxCommands) {
                return true;
            }
            this.commandTimestamps.merge(now, 1, Integer::sum);
            return false;
        }

        synchronized void push(long now, String message) {
            this.messageTimestamps.merge(now, 1, Integer::sum);
            this.lastMessageAt = now;
            this.lastMessage = message.toLowerCase();
        }

        synchronized boolean isRepeated(String message) {
            return !message.isEmpty() && message.toLowerCase().equals(this.lastMessage);
        }

        synchronized int addViolation(long now) {
            if (now - this.lastViolationAt > 5_000L) {
                this.violations = 0;
            }
            this.lastViolationAt = now;
            return ++this.violations;
        }

        synchronized void resetViolations() {
            this.violations = 0;
        }

        void mute(long now, long durationMs) {
            this.mutedUntil = now + Math.max(1000L, durationMs);
        }

        boolean isMuted(long now) {
            return this.mutedUntil > now;
        }

        private void prune(Map<Long, Integer> map, long now, long windowMs) {
            map.entrySet().removeIf(entry -> now - entry.getKey() > windowMs);
        }
    }
}

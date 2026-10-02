package dev.waterdog.anti.module;

import dev.waterdog.anti.WaterdogAnti;
import dev.waterdog.waterdogpe.event.EventManager;
import dev.waterdog.waterdogpe.event.defaults.PlayerAuthenticatedEvent;
import dev.waterdog.waterdogpe.event.defaults.PlayerDisconnectedEvent;
import dev.waterdog.waterdogpe.network.protocol.ProtocolVersion;
import dev.waterdog.waterdogpe.network.protocol.user.LoginData;
import dev.waterdog.waterdogpe.player.ProxiedPlayer;

import java.net.InetAddress;
import java.net.InetSocketAddress;
import java.util.ArrayDeque;
import java.util.Deque;
import java.util.List;
import java.util.Map;
import java.util.Objects;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.TimeUnit;
import java.util.regex.Pattern;
import java.util.regex.PatternSyntaxException;

/**
 * First line of defence: everything that happens <b>before</b> a player owns a downstream connection.
 * <p>
 * Hooks {@link PlayerAuthenticatedEvent}, which is fired right after the login packet was decoded and
 * before the {@link ProxiedPlayer} object is created/linked to a lobby - the cheapest possible place to
 * drop bots, spoofed names and flooded addresses.
 */
public class JoinGuard {

    private final WaterdogAnti plugin;
    private final Map<InetAddress, IpRecord> ipRecords = new ConcurrentHashMap<>();

    private final Object waveLock = new Object();
    private long waveWindowStart = System.currentTimeMillis();
    private int waveCount;
    private long waveMutedUntil;

    private volatile int joinsAccepted;
    private volatile int joinsRejected;

    public JoinGuard(WaterdogAnti plugin) {
        this.plugin = plugin;
    }

    public void enable() {
        EventManager events = this.plugin.getProxy().getEventManager();
        events.subscribe(PlayerAuthenticatedEvent.class, this::onAuthenticated);
        events.subscribe(PlayerDisconnectedEvent.class, this::onDisconnected);
        this.plugin.getLogger().info("JoinGuard enabled.");
    }

    private void onAuthenticated(PlayerAuthenticatedEvent event) {
        LoginData data = event.getLoginData();
        if (data == null) {
            return;
        }

        String name = data.getDisplayName() == null ? "" : data.getDisplayName();
        if (this.isBypassed(name)) {
            this.joinsAccepted++;
            return;
        }

        // 1) Xbox authenticated accounts only. This alone removes the vast majority of cheap bots.
        if (this.setting("join-guard.require-xbox-auth", true) && !data.isXboxAuthed()) {
            this.reject(event, "§cOnly Xbox Live accounts are allowed on this server.");
            return;
        }

        // 2) Name pattern
        String regex = this.setting("join-guard.name-regex", "");
        if (!regex.isBlank()) {
            try {
                if (!Pattern.compile(regex).matcher(name).matches()) {
                    this.reject(event, this.setting("join-guard.name-message", "§cYour name is not allowed on this server."));
                    return;
                }
            } catch (PatternSyntaxException e) {
                this.plugin.getLogger().warn("join-guard.name-regex is not a valid regex, skipping name check.");
            }
        }

        // 3) Blocked name parts
        for (String blocked : this.plugin.settings().list("join-guard.blocked-names")) {
            if (!blocked.isBlank() && name.toLowerCase().contains(blocked.toLowerCase())) {
                this.reject(event, "§cYour name is not allowed on this server.");
                return;
            }
        }

        // 4) Whitelist
        if (this.setting("join-guard.whitelist-enabled", false)) {
            boolean allowed = false;
            for (String entry : this.plugin.settings().list("join-guard.whitelist")) {
                if (entry.equalsIgnoreCase(name)) {
                    allowed = true;
                    break;
                }
            }
            if (!allowed) {
                this.reject(event, "§cThis server is whitelisted.");
                return;
            }
        }

        // 5) Protocol window (Bedrock 1.20 -> 1.26.30 and anything else the proxy supports)
        if (this.setting("join-guard.protocol-guard-enabled", false)) {
            ProtocolVersion protocolVersion = data.getProtocol();
            int protocolId = protocolVersion == null ? 0 : protocolVersion.getProtocol();
            int min = this.setting("join-guard.min-protocol", 0);
            int max = this.setting("join-guard.max-protocol", 0);
            if ((min > 0 && protocolId < min) || (max > 0 && protocolId > max)) {
                this.reject(event, this.setting("join-guard.protocol-message", "§cYour game version is not supported.")
                        + " §7(protocol " + protocolId + ")");
                return;
            }
        }

        // 6) Name already used by a *different* XUID (name spoofing / impersonation)
        if (this.setting("join-guard.block-duplicate-name", true)) {
            ProxiedPlayer online = this.plugin.getProxy().getPlayer(name);
            if (online != null && !Objects.equals(online.getXuid(), data.getXuid())) {
                this.reject(event, "§cThis name is already used by another player.");
                return;
            }
        }

        // 7) Name <-> XUID binding from players.db
        if (this.setting("player-store.enabled", true) && data.getXuid() != null) {
            String previous = this.plugin.playerStore().nameOf(data.getXuid());
            if (previous != null && !previous.equalsIgnoreCase(name)) {
                this.plugin.violations().alert("PlayerStore", name + " changed name from " + previous
                        + " (xuid " + data.getXuid() + ")");
                if (this.setting("join-guard.strict-name-binding", false)) {
                    this.reject(event, "§cYou must use your previous name: " + previous);
                    return;
                }
            }
            String boundXuid = this.plugin.playerStore().xuidOf(name);
            if (boundXuid != null && !boundXuid.equals(data.getXuid())) {
                this.plugin.violations().alert("PlayerStore", "Name " + name + " is already bound to another XUID!");
                if (this.setting("join-guard.strict-name-binding", false)) {
                    this.reject(event, "§cThis name belongs to another account.");
                    return;
                }
            }
        }

        // 8) Per-IP join flooding
        InetAddress address = this.addressOf(event);
        if (address != null) {
            int maxJoins = this.setting("join-guard.max-joins-per-ip", 4);
            int windowSeconds = this.setting("join-guard.join-window-seconds", 60);
            IpRecord record = this.ipRecords.computeIfAbsent(address, key -> new IpRecord());
            int joins = record.countWithin(windowSeconds * 1000L, System.currentTimeMillis());
            if (maxJoins > 0 && joins >= maxJoins) {
                this.blockAddress(address, "join flood (" + (joins + 1) + " joins from one IP)");
                this.reject(event, "§cToo many connections from your address. Try again later.");
                return;
            }
            record.addJoin(System.currentTimeMillis());
        }

        // 9) Network-wide join wave (bot attack pattern)
        if (this.detectWave()) {
            if (address != null && this.setting("join-guard.wave-block-ips", true)) {
                this.blockAddress(address, "join wave detected");
            }
            this.reject(event, "§cThe server is under heavy load. Please try again in a moment.");
            return;
        }

        if (this.setting("player-store.enabled", true)) {
            this.plugin.playerStore().record(data.getXuid(), name);
        }
        this.joinsAccepted++;
    }

    private void onDisconnected(PlayerDisconnectedEvent event) {
        ProxiedPlayer player = event.getPlayer();
        if (player == null) {
            return;
        }
        InetAddress address = player.getAddress() == null ? null : player.getAddress().getAddress();
        if (address != null && this.setting("player-store.enabled", true)) {
            this.plugin.playerStore().record(player.getXuid(), player.getName());
        }
    }

    /**
     * @return true when the join rate crossed {@code join-guard.wave-threshold}
     */
    private boolean detectWave() {
        int threshold = this.setting("join-guard.wave-threshold", 40);
        int windowSeconds = this.setting("join-guard.wave-window-seconds", 60);
        if (threshold <= 0) {
            return false;
        }
        long now = System.currentTimeMillis();
        synchronized (this.waveLock) {
            if (now - this.waveWindowStart > windowSeconds * 1000L) {
                this.waveWindowStart = now;
                this.waveCount = 0;
            }
            this.waveCount++;
            if (this.waveCount <= threshold) {
                return false;
            }
            if (now > this.waveMutedUntil) {
                this.waveMutedUntil = now + 30_000L;
                this.plugin.violations().alert("JoinWave", this.waveCount + " joins in "
                        + windowSeconds + "s - possible bot wave!");
            }
        }
        return true;
    }

    private void reject(PlayerAuthenticatedEvent event, String reason) {
        event.setCancelReason(reason);
        event.setCancelled(true);
        this.joinsRejected++;
    }

    private void blockAddress(InetAddress address, String reason) {
        int seconds = this.setting("join-guard.block-duration-seconds", 300);
        if (this.setting("join-guard.block-ip-on-exceed", true) && seconds > 0) {
            this.plugin.getProxy().getSecurityManager().blockAddress(address, seconds, TimeUnit.SECONDS);
        }
        this.plugin.violations().alert("JoinGuard", "Blocked " + address.getHostAddress() + " for "
                + seconds + "s: " + reason);
    }

    /**
     * Removes IP records that have been idle for a while. Called once per second.
     */
    public void tick(long now) {
        this.ipRecords.entrySet().removeIf(entry -> entry.getValue().isIdle(now));
    }

    public void unblock(String ip) {
        try {
            InetAddress address = InetAddress.getByName(ip);
            this.ipRecords.remove(address);
            this.plugin.getProxy().getSecurityManager().unblockAddress(address);
            this.plugin.getLogger().info("Unblocked " + ip);
        } catch (Exception e) {
            this.plugin.getLogger().warn("Cannot resolve/block " + ip + ": " + e.getMessage());
        }
    }

    public int blockedIps() {
        return this.ipRecords.size();
    }

    public int accepted() {
        return this.joinsAccepted;
    }

    public int rejected() {
        return this.joinsRejected;
    }

    private InetAddress addressOf(PlayerAuthenticatedEvent event) {
        InetSocketAddress address = event.getAddress();
        return address == null ? null : address.getAddress();
    }

    private boolean isBypassed(String name) {
        if (name.isEmpty()) {
            return false;
        }
        for (String entry : this.plugin.settings().list("general.bypass-names")) {
            if (entry.equalsIgnoreCase(name)) {
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

    private static final class IpRecord {
        private static final long IDLE_TIMEOUT_MS = 15 * 60 * 1000L;

        private final Deque<Long> joins = new ArrayDeque<>();
        private volatile long lastSeen = System.currentTimeMillis();

        synchronized void addJoin(long now) {
            this.joins.addLast(now);
            this.lastSeen = now;
        }

        synchronized int countWithin(long windowMs, long now) {
            while (!this.joins.isEmpty() && now - this.joins.peekFirst() > windowMs) {
                this.joins.pollFirst();
            }
            return this.joins.size();
        }

        boolean isIdle(long now) {
            return now - this.lastSeen > IDLE_TIMEOUT_MS;
        }
    }
}

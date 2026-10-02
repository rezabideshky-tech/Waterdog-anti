package com.arvangaming.shield;

import dev.waterdog.waterdogpe.event.defaults.InitialServerConnectedEvent;
import dev.waterdog.waterdogpe.event.defaults.PlayerAuthenticatedEvent;
import dev.waterdog.waterdogpe.network.protocol.user.LoginData;
import dev.waterdog.waterdogpe.player.ProxiedPlayer;

import java.net.InetAddress;
import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.security.SecureRandom;
import java.util.Arrays;
import java.util.EnumSet;
import java.util.HexFormat;
import java.util.Locale;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicLong;
import java.util.concurrent.atomic.LongAdder;

/**
 * Lightweight login-time risk scoring. This class deliberately avoids DNS, disk, network calls,
 * device IDs, and protocol-version heuristics on Waterdog's connection/event threads.
 */
final class AntiBotEngine {
    private enum Signal {
        NETWORK,
        IDENTITY,
        GLOBAL,
        AUTHENTICATION
    }

    private static final HexFormat HEX = HexFormat.of();
    private static final ThreadLocal<MessageDigest> SHA_256 = ThreadLocal.withInitial(AntiBotEngine::newSha256);

    private final ArvanShieldPlugin plugin;
    private final byte[] hashSalt = new byte[32];
    private final ConcurrentHashMap<String, TokenBucket> buckets = new ConcurrentHashMap<>();
    private final ConcurrentHashMap<String, Long> recentlySuccessfulXuids = new ConcurrentHashMap<>();
    // Kept outside the bounded per-key map so global protection remains available at its memory cap.
    private final TokenBucket globalBucket;

    private final LongAdder attempts = new LongAdder();
    private final LongAdder allowed = new LongAdder();
    private final LongAdder rejected = new LongAdder();
    private final LongAdder wouldReject = new LongAdder();
    private final LongAdder suppressedRejectLogs = new LongAdder();
    private final AtomicLong nextRejectLogNanos = new AtomicLong();
    private final AtomicLong lastRiskScore = new AtomicLong();

    private volatile AntiBotConfig config;
    private volatile AntiBotConfig.Mode mode;
    private volatile boolean active = true;

    AntiBotEngine(ArvanShieldPlugin plugin, AntiBotConfig config) {
        this.plugin = plugin;
        new SecureRandom().nextBytes(this.hashSalt);
        this.config = config;
        this.mode = config.initialMode;
        this.globalBucket = new TokenBucket(config.globalBurst, System.nanoTime());
    }

    void onAuthenticated(PlayerAuthenticatedEvent event) {
        if (!this.active) {
            return;
        }

        this.attempts.increment();
        AntiBotConfig currentConfig = this.config;
        AntiBotConfig.Mode currentMode = this.mode;
        if (currentMode == AntiBotConfig.Mode.OFF) {
            this.allowed.increment();
            this.lastRiskScore.set(0);
            return;
        }

        Decision decision = this.evaluate(event, currentConfig, System.nanoTime());
        this.lastRiskScore.set(decision.score);
        boolean wouldRejectInBalanced = this.exceedsThreshold(decision, currentConfig, AntiBotConfig.Mode.BALANCED);
        boolean shouldReject = switch (currentMode) {
            case OFF, OBSERVE -> false;
            case BALANCED, ATTACK -> this.exceedsThreshold(decision, currentConfig, currentMode);
        };

        if (currentMode == AntiBotConfig.Mode.OBSERVE && wouldRejectInBalanced) {
            this.wouldReject.increment();
            long count = this.wouldReject.sum();
            if (count % currentConfig.observeSampleEvery == 0) {
                this.plugin.getLogger().info("[observe] Login risk sample: score=" + decision.score
                        + ", signals=" + decision.signalNames()
                        + ", evidence=" + decision.evidenceSummary()
                        + "; no connection was blocked.");
            }
        }

        if (shouldReject) {
            event.setCancelReason(currentConfig.rejectedMessage);
            event.setCancelled(true);
            this.rejected.increment();
            this.logRejected(currentMode, decision);
            return;
        }

        this.allowed.increment();
    }

    void onInitialServerConnected(InitialServerConnectedEvent event) {
        if (!this.active) {
            return;
        }
        AntiBotConfig currentConfig = this.config;
        int trustSeconds = currentConfig.knownPlayerTrustSeconds;
        if (trustSeconds <= 0) {
            return;
        }

        ProxiedPlayer player = event.getPlayer();
        LoginData loginData = player == null ? null : player.getLoginData();
        String xuid = loginData == null ? null : loginData.getXuid();
        String normalizedXuid = normalizeXuid(xuid);
        if (normalizedXuid.isEmpty()) {
            return;
        }

        if (this.recentlySuccessfulXuids.size() >= Math.max(100, currentConfig.maxTrackedBuckets / 2)) {
            return;
        }
        long expiresAt = System.nanoTime() + TimeUnit.SECONDS.toNanos(trustSeconds);
        this.recentlySuccessfulXuids.put(hash("xuid", normalizedXuid), expiresAt);
    }

    private Decision evaluate(PlayerAuthenticatedEvent event, AntiBotConfig currentConfig, long now) {
        LoginData loginData = event.getLoginData();
        Decision decision = new Decision();
        String normalizedXuid = normalizeXuid(loginData.getXuid());

        if (currentConfig.requireXboxAuthentication && !loginData.isXboxAuthed()) {
            decision.hardReject = true;
            decision.score += 100;
            decision.signals.add(Signal.AUTHENTICATION);
            decision.evidence.add("xbox-auth-required");
        }

        if (!normalizedXuid.isEmpty()) {
            boolean identityAllowed = this.consumeBucket(
                    "xuid:" + hash("xuid", normalizedXuid),
                    currentConfig.xuidBurst,
                    currentConfig.xuidRefillPerSecond,
                    currentConfig,
                    now);
            if (!identityAllowed) {
                decision.score += currentConfig.xuidRateScore;
                decision.signals.add(Signal.IDENTITY);
                decision.evidence.add("xuid-rate");
            }
        }

        InetSocketAddress socketAddress = event.getAddress();
        InetAddress inetAddress = socketAddress == null ? null : socketAddress.getAddress();
        if (inetAddress != null) {
            byte[] ipBytes = inetAddress.getAddress();
            String ipKey = "ip:" + hash("ip", HEX.formatHex(ipBytes));
            if (!this.consumeBucket(ipKey, currentConfig.ipBurst,
                    currentConfig.ipRefillPerSecond, currentConfig, now)) {
                decision.score += currentConfig.ipRateScore;
                decision.signals.add(Signal.NETWORK);
                decision.evidence.add("ip-rate");
            }

            byte[] subnetBytes = ipBytes.clone();
            int significantBytes = ipBytes.length == 4 ? 3 : 8;
            Arrays.fill(subnetBytes, significantBytes, subnetBytes.length, (byte) 0);
            String subnetKey = "subnet:" + hash("subnet", HEX.formatHex(subnetBytes));
            if (!this.consumeBucket(subnetKey, currentConfig.subnetBurst,
                    currentConfig.subnetRefillPerSecond, currentConfig, now)) {
                decision.score += currentConfig.subnetRateScore;
                decision.signals.add(Signal.NETWORK);
                decision.evidence.add("subnet-rate");
            }
        }

        if (!this.globalBucket.tryConsume(currentConfig.globalBurst,
                currentConfig.globalRefillPerSecond, now)) {
            decision.score += currentConfig.globalRateScore;
            decision.signals.add(Signal.GLOBAL);
            decision.evidence.add("global-rate");
        }

        boolean configuredTrusted = !normalizedXuid.isEmpty()
                && currentConfig.trustedXuids.contains(normalizedXuid);
        boolean recentlySuccessful = !normalizedXuid.isEmpty()
                && this.isRecentlySuccessful(normalizedXuid, now);
        if ((configuredTrusted || recentlySuccessful) && decision.score > 0) {
            decision.score = Math.max(0, decision.score - currentConfig.trustedXuidDiscount);
            decision.evidence.add("trusted-xuid-discount");
        }

        return decision;
    }

    private static String normalizeXuid(String xuid) {
        if (xuid == null || xuid.isBlank()) {
            return "";
        }
        String normalized = xuid.trim().toLowerCase(Locale.ROOT);
        // Some offline/unverified configurations use a shared placeholder; never treat it as identity.
        return normalized.equals("0") || normalized.equals("0000000000000000")
                || normalized.equals("null") || normalized.equals("undefined") ? "" : normalized;
    }

    private boolean isRecentlySuccessful(String normalizedXuid, long now) {
        String key = hash("xuid", normalizedXuid);
        Long expiresAt = this.recentlySuccessfulXuids.get(key);
        if (expiresAt == null) {
            return false;
        }
        if (now - expiresAt >= 0) {
            this.recentlySuccessfulXuids.remove(key, expiresAt);
            return false;
        }
        return true;
    }

    private boolean consumeBucket(String key, int capacity, double refillPerSecond,
                                  AntiBotConfig currentConfig, long now) {
        TokenBucket bucket = this.buckets.get(key);
        if (bucket == null) {
            // Keep memory bounded even if an attacker rotates source addresses. When the map is
            // full, per-key accounting fails open; the independent global bucket remains active.
            if (this.buckets.size() >= currentConfig.maxTrackedBuckets) {
                return true;
            }
            TokenBucket fresh = new TokenBucket(capacity, now);
            TokenBucket previous = this.buckets.putIfAbsent(key, fresh);
            bucket = previous == null ? fresh : previous;
        }
        return bucket.tryConsume(capacity, refillPerSecond, now);
    }

    private boolean exceedsThreshold(Decision decision, AntiBotConfig currentConfig, AntiBotConfig.Mode targetMode) {
        if (decision.hardReject) {
            return true;
        }
        int threshold = targetMode == AntiBotConfig.Mode.ATTACK
                ? currentConfig.attackThreshold
                : currentConfig.balancedThreshold;
        return decision.score >= threshold
                && decision.signals.size() >= currentConfig.minimumIndependentSignals;
    }

    private void logRejected(AntiBotConfig.Mode rejectedMode, Decision decision) {
        long now = System.nanoTime();
        while (true) {
            long nextAllowed = this.nextRejectLogNanos.get();
            if (nextAllowed != 0 && now - nextAllowed < 0) {
                this.suppressedRejectLogs.increment();
                return;
            }
            long next = now + TimeUnit.SECONDS.toNanos(3);
            if (this.nextRejectLogNanos.compareAndSet(nextAllowed, next)) {
                long suppressed = this.suppressedRejectLogs.sumThenReset();
                this.plugin.getLogger().warn("Rejected a high-risk login (mode=" + rejectedMode.configName()
                        + ", score=" + decision.score
                        + ", signals=" + decision.signalNames()
                        + ", evidence=" + decision.evidenceSummary()
                        + (suppressed > 0 ? ", additional blocked logins suppressed=" + suppressed : "") + ").");
                return;
            }
        }
    }

    void setMode(AntiBotConfig.Mode mode) {
        this.mode = mode;
    }

    AntiBotConfig.Mode getMode() {
        return this.mode;
    }

    void reload(AntiBotConfig config) {
        this.config = config;
        this.mode = config.initialMode;
    }

    Snapshot snapshot() {
        return new Snapshot(this.mode, this.attempts.sum(), this.allowed.sum(), this.rejected.sum(),
                this.wouldReject.sum(), this.lastRiskScore.get(), this.buckets.size());
    }

    void cleanup() {
        if (!this.active) {
            return;
        }
        long now = System.nanoTime();
        long idleNanos = TimeUnit.SECONDS.toNanos(this.config.idleExpirySeconds);
        this.buckets.entrySet().removeIf(entry -> now - entry.getValue().lastAccessNanos() >= idleNanos);
        this.recentlySuccessfulXuids.entrySet().removeIf(entry -> now - entry.getValue() >= 0);
    }

    void shutdown() {
        this.active = false;
        this.buckets.clear();
        this.recentlySuccessfulXuids.clear();
    }

    private String hash(String namespace, String value) {
        MessageDigest digest = SHA_256.get();
        digest.reset();
        digest.update(this.hashSalt);
        digest.update(namespace.getBytes(StandardCharsets.UTF_8));
        digest.update((byte) 0);
        return HEX.formatHex(digest.digest(value.getBytes(StandardCharsets.UTF_8)));
    }

    private static MessageDigest newSha256() {
        try {
            return MessageDigest.getInstance("SHA-256");
        } catch (NoSuchAlgorithmException exception) {
            throw new IllegalStateException("SHA-256 is required by the Java platform", exception);
        }
    }

    record Snapshot(AntiBotConfig.Mode mode, long attempts, long allowed, long rejected,
                    long wouldReject, long lastRiskScore, int trackedBuckets) {
    }

    private static final class Decision {
        private final EnumSet<Signal> signals = EnumSet.noneOf(Signal.class);
        private final java.util.List<String> evidence = new java.util.ArrayList<>(4);
        private int score;
        private boolean hardReject;

        private String signalNames() {
            return this.signals.isEmpty() ? "none" : this.signals.toString();
        }

        private String evidenceSummary() {
            return this.evidence.isEmpty() ? "none" : String.join(",", this.evidence);
        }
    }

    private static final class TokenBucket {
        private double tokens;
        private long lastRefillNanos;
        private volatile long lastAccessNanos;

        private TokenBucket(int capacity, long now) {
            this.tokens = capacity;
            this.lastRefillNanos = now;
            this.lastAccessNanos = now;
        }

        private synchronized boolean tryConsume(int capacity, double refillPerSecond, long now) {
            double elapsedSeconds = Math.max(0L, now - this.lastRefillNanos) / 1_000_000_000.0;
            this.tokens = Math.min(capacity, this.tokens + elapsedSeconds * refillPerSecond);
            this.lastRefillNanos = now;
            this.lastAccessNanos = now;
            if (this.tokens < 1.0) {
                return false;
            }
            this.tokens -= 1.0;
            return true;
        }

        private long lastAccessNanos() {
            return this.lastAccessNanos;
        }
    }
}

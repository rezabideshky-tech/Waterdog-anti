package com.arvangaming.shield;

import dev.waterdog.waterdogpe.utils.config.Configuration;

import java.util.Collections;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;

final class AntiBotConfig {
    enum Mode {
        OFF,
        OBSERVE,
        BALANCED,
        ATTACK;

        static Mode parse(String value) {
            if (value == null) {
                return OBSERVE;
            }
            try {
                return Mode.valueOf(value.trim().toUpperCase(Locale.ROOT));
            } catch (IllegalArgumentException ignored) {
                return OBSERVE;
            }
        }

        String configName() {
            return this.name().toLowerCase(Locale.ROOT);
        }
    }

    final Mode initialMode;
    final boolean requireXboxAuthentication;
    final Set<String> trustedXuids;

    final int ipBurst;
    final double ipRefillPerSecond;
    final int subnetBurst;
    final double subnetRefillPerSecond;
    final int xuidBurst;
    final double xuidRefillPerSecond;
    final int globalBurst;
    final double globalRefillPerSecond;

    final int balancedThreshold;
    final int attackThreshold;
    final int minimumIndependentSignals;
    final int ipRateScore;
    final int subnetRateScore;
    final int xuidRateScore;
    final int globalRateScore;
    final int trustedXuidDiscount;

    final int maxTrackedBuckets;
    final int idleExpirySeconds;
    final int knownPlayerTrustSeconds;
    final int observeSampleEvery;
    final String rejectedMessage;

    private AntiBotConfig(Configuration source) {
        this.initialMode = Mode.parse(readString(source, "mode", "observe"));
        this.requireXboxAuthentication = readBoolean(source, "security.require-xbox-authentication", false);

        List<String> rawTrustedXuids = readStringList(source, "trusted-xuids");
        Set<String> normalizedXuids = new LinkedHashSet<>();
        for (String xuid : rawTrustedXuids) {
            if (xuid != null && !xuid.isBlank()) {
                normalizedXuids.add(xuid.trim().toLowerCase(Locale.ROOT));
            }
        }
        this.trustedXuids = Collections.unmodifiableSet(normalizedXuids);

        this.ipBurst = readInt(source, "limits.ip.burst", 12, 1, 1_000_000);
        this.ipRefillPerSecond = readDouble(source, "limits.ip.refill-per-second", 1.0, 0.0, 100_000.0);
        this.subnetBurst = readInt(source, "limits.subnet.burst", 80, 1, 1_000_000);
        this.subnetRefillPerSecond = readDouble(source, "limits.subnet.refill-per-second", 10.0, 0.0, 100_000.0);
        this.xuidBurst = readInt(source, "limits.xuid.burst", 6, 1, 1_000_000);
        this.xuidRefillPerSecond = readDouble(source, "limits.xuid.refill-per-second", 0.5, 0.0, 100_000.0);
        this.globalBurst = readInt(source, "limits.global.burst", 300, 1, 1_000_000);
        this.globalRefillPerSecond = readDouble(source, "limits.global.refill-per-second", 75.0, 0.0, 100_000.0);

        this.balancedThreshold = readInt(source, "risk.balanced-threshold", 75, 1, 10_000);
        this.attackThreshold = readInt(source, "risk.attack-threshold", 60, 1, 10_000);
        this.minimumIndependentSignals = readInt(source, "risk.minimum-independent-signals", 2, 1, 3);
        this.ipRateScore = readInt(source, "risk.scores.ip-rate", 25, 0, 10_000);
        this.subnetRateScore = readInt(source, "risk.scores.subnet-rate", 15, 0, 10_000);
        this.xuidRateScore = readInt(source, "risk.scores.xuid-rate", 55, 0, 10_000);
        this.globalRateScore = readInt(source, "risk.scores.global-rate", 15, 0, 10_000);
        this.trustedXuidDiscount = readInt(source, "risk.scores.trusted-xuid-discount", 20, 0, 10_000);

        this.maxTrackedBuckets = readInt(source, "storage.max-tracked-buckets", 50_000, 1_000, 500_000);
        this.idleExpirySeconds = readInt(source, "storage.idle-expiry-seconds", 600, 30, 86_400);
        this.knownPlayerTrustSeconds = readInt(source, "storage.known-player-trust-seconds", 3_600, 0, 604_800);
        this.observeSampleEvery = readInt(source, "logging.observe-sample-every", 50, 1, 1_000_000);
        this.rejectedMessage = readString(source, "messages.rejected",
                "§cورود شما موقتاً محدود شد؛ لطفاً چند لحظه بعد دوباره تلاش کنید.");
    }

    static AntiBotConfig load(Configuration source) {
        return new AntiBotConfig(source);
    }

    private static String readString(Configuration source, String key, String fallback) {
        try {
            String value = source.getString(key, fallback);
            return value == null || value.isBlank() ? fallback : value;
        } catch (RuntimeException ignored) {
            return fallback;
        }
    }

    private static boolean readBoolean(Configuration source, String key, boolean fallback) {
        try {
            Boolean value = source.getBoolean(key, fallback);
            return value == null ? fallback : value;
        } catch (RuntimeException ignored) {
            return fallback;
        }
    }

    private static int readInt(Configuration source, String key, int fallback, int minimum, int maximum) {
        try {
            Integer value = source.getInt(key, fallback);
            if (value == null) {
                return fallback;
            }
            return Math.max(minimum, Math.min(maximum, value));
        } catch (RuntimeException ignored) {
            return fallback;
        }
    }

    private static double readDouble(Configuration source, String key, double fallback, double minimum, double maximum) {
        try {
            Double value = source.getDouble(key, fallback);
            if (value == null || !Double.isFinite(value)) {
                return fallback;
            }
            return Math.max(minimum, Math.min(maximum, value));
        } catch (RuntimeException ignored) {
            return fallback;
        }
    }

    private static List<String> readStringList(Configuration source, String key) {
        try {
            Object raw = source.get(key, List.of());
            if (!(raw instanceof List<?> values)) {
                return List.of();
            }
            return values.stream()
                    .filter(String.class::isInstance)
                    .map(String.class::cast)
                    .toList();
        } catch (RuntimeException ignored) {
            return List.of();
        }
    }
}

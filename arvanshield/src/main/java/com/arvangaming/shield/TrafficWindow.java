package com.arvangaming.shield;

import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.LongAdder;

/**
 * Lock-free counters for WaterdogPE's aggregate network metrics. This is observational: WaterdogPE's
 * public NetworkMetrics API does not expose a per-player packet cancellation hook.
 */
final class TrafficWindow {
    private final LongAdder inboundDecodedBytes = new LongAdder();
    private final LongAdder compressedBytes = new LongAdder();
    private final LongAdder passedThroughPackets = new LongAdder();
    private final LongAdder droppedBytes = new LongAdder();
    private final LongAdder oversizedPacketQueues = new LongAdder();

    private long lastSampleNanos;
    private int consecutiveHighWindows;
    private long pressureUntilNanos;
    private volatile Snapshot snapshot = Snapshot.empty();

    TrafficWindow() {
        this(System.nanoTime());
    }

    TrafficWindow(long initialSampleNanos) {
        this.lastSampleNanos = initialSampleNanos;
    }

    void recordInboundDecodedBytes(int count) {
        if (count > 0) {
            this.inboundDecodedBytes.add(count);
        }
    }

    void recordCompressedBytes(int count) {
        if (count > 0) {
            this.compressedBytes.add(count);
        }
    }

    void recordPassedThroughPackets(int count) {
        if (count > 0) {
            this.passedThroughPackets.add(count);
        }
    }

    void recordDroppedBytes(int count) {
        if (count > 0) {
            this.droppedBytes.add(count);
        }
    }

    void recordOversizedPacketQueue() {
        this.oversizedPacketQueues.increment();
    }

    synchronized Snapshot sample(long nowNanos, boolean enabled, long pressureThresholdBytesPerSecond,
                                 int sustainedWindows, int cooldownSeconds) {
        long elapsedNanos = Math.max(1L, nowNanos - this.lastSampleNanos);
        this.lastSampleNanos = nowNanos;

        long inboundBytes = this.inboundDecodedBytes.sumThenReset();
        long compressed = this.compressedBytes.sumThenReset();
        long passedPackets = this.passedThroughPackets.sumThenReset();
        long dropped = this.droppedBytes.sumThenReset();
        long overflowedQueues = this.oversizedPacketQueues.sumThenReset();

        long inboundRate = perSecond(inboundBytes, elapsedNanos);
        long compressedRate = perSecond(compressed, elapsedNanos);
        long packetRate = perSecond(passedPackets, elapsedNanos);
        long droppedRate = perSecond(dropped, elapsedNanos);

        int requiredWindows = Math.max(1, sustainedWindows);
        if (!enabled || pressureThresholdBytesPerSecond <= 0L) {
            this.consecutiveHighWindows = 0;
            this.pressureUntilNanos = 0L;
        } else if (inboundRate >= pressureThresholdBytesPerSecond) {
            this.consecutiveHighWindows = Math.min(requiredWindows, this.consecutiveHighWindows + 1);
            if (this.consecutiveHighWindows >= requiredWindows) {
                this.pressureUntilNanos = nowNanos
                        + TimeUnit.SECONDS.toNanos(Math.max(1, cooldownSeconds));
            }
        } else {
            this.consecutiveHighWindows = 0;
        }

        boolean highPressure = this.pressureUntilNanos != 0L && nowNanos - this.pressureUntilNanos < 0L;
        this.snapshot = new Snapshot(inboundRate, compressedRate, packetRate, droppedRate,
                overflowedQueues, this.consecutiveHighWindows, highPressure);
        return this.snapshot;
    }

    Snapshot snapshot() {
        return this.snapshot;
    }

    synchronized void resetPressure() {
        this.consecutiveHighWindows = 0;
        this.pressureUntilNanos = 0L;
        Snapshot current = this.snapshot;
        this.snapshot = new Snapshot(current.inboundDecodedBytesPerSecond(),
                current.compressedBytesPerSecond(), current.passedThroughPacketsPerSecond(),
                current.droppedBytesPerSecond(), current.oversizedPacketQueues(), 0, false);
    }

    private static long perSecond(long count, long elapsedNanos) {
        if (count <= 0L) {
            return 0L;
        }
        double rate = count * (double) TimeUnit.SECONDS.toNanos(1) / elapsedNanos;
        return rate >= Long.MAX_VALUE ? Long.MAX_VALUE : (long) rate;
    }

    record Snapshot(long inboundDecodedBytesPerSecond,
                    long compressedBytesPerSecond,
                    long passedThroughPacketsPerSecond,
                    long droppedBytesPerSecond,
                    long oversizedPacketQueues,
                    int consecutiveHighWindows,
                    boolean highPressure) {
        static Snapshot empty() {
            return new Snapshot(0L, 0L, 0L, 0L, 0L, 0, false);
        }
    }
}

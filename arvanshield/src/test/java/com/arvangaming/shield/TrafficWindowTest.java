package com.arvangaming.shield;

import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

class TrafficWindowTest {
    private static final long SECOND = 1_000_000_000L;

    @Test
    void pressureRequiresSeveralConsecutiveWindowsAndExpires() {
        TrafficWindow window = new TrafficWindow(0L);

        window.recordInboundDecodedBytes(1_000);
        assertFalse(window.sample(SECOND, true, 1_000L, 3, 5).highPressure());
        window.recordInboundDecodedBytes(1_000);
        assertFalse(window.sample(2 * SECOND, true, 1_000L, 3, 5).highPressure());
        window.recordInboundDecodedBytes(1_000);
        assertTrue(window.sample(3 * SECOND, true, 1_000L, 3, 5).highPressure());

        assertTrue(window.sample(4 * SECOND, true, 1_000L, 3, 5).highPressure());
        assertFalse(window.sample(9 * SECOND, true, 1_000L, 3, 5).highPressure());
    }

    @Test
    void zeroThresholdAndDisabledMonitorNeverShedLogins() {
        TrafficWindow window = new TrafficWindow(0L);
        window.recordInboundDecodedBytes(50_000_000);

        assertFalse(window.sample(SECOND, true, 0L, 1, 15).highPressure());
        window.recordInboundDecodedBytes(50_000_000);
        assertFalse(window.sample(2 * SECOND, false, 1L, 1, 15).highPressure());
    }

    @Test
    void resetPressureClearsTheTriggerWithoutDiscardingTrafficRates() {
        TrafficWindow window = new TrafficWindow(0L);
        window.recordInboundDecodedBytes(1_000);
        window.sample(SECOND, true, 1_000L, 1, 15);
        assertTrue(window.snapshot().highPressure());

        window.resetPressure();

        assertFalse(window.snapshot().highPressure());
        assertEquals(0, window.snapshot().consecutiveHighWindows());
        assertEquals(1_000L, window.snapshot().inboundDecodedBytesPerSecond());
    }

    @Test
    void rateCountersAreWindowedAndIgnoreNegativeValues() {
        TrafficWindow window = new TrafficWindow(0L);
        window.recordInboundDecodedBytes(1_024);
        window.recordInboundDecodedBytes(-50);
        window.recordCompressedBytes(2_048);
        window.recordPassedThroughPackets(20);
        window.recordDroppedBytes(512);
        window.recordOversizedPacketQueue();

        TrafficWindow.Snapshot snapshot = window.sample(SECOND, true, 0L, 1, 10);

        assertEquals(1_024L, snapshot.inboundDecodedBytesPerSecond());
        assertEquals(2_048L, snapshot.compressedBytesPerSecond());
        assertEquals(20L, snapshot.passedThroughPacketsPerSecond());
        assertEquals(512L, snapshot.droppedBytesPerSecond());
        assertEquals(1L, snapshot.oversizedPacketQueues());
    }
}

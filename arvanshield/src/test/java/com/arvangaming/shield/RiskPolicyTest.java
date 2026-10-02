package com.arvangaming.shield;

import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

class RiskPolicyTest {
    @Test
    void oneSignalNeverRejectsEvenWhenItsScoreIsHigh() {
        assertFalse(RiskPolicy.shouldReject(120, 1, 75, 2, false));
    }

    @Test
    void requiresBothThresholdAndIndependentSignalCount() {
        assertFalse(RiskPolicy.shouldReject(74, 2, 75, 2, false));
        assertFalse(RiskPolicy.shouldReject(120, 1, 75, 2, false));
        assertTrue(RiskPolicy.shouldReject(75, 2, 75, 2, false));
    }

    @Test
    void explicitAuthenticationPolicyCanRejectIndependently() {
        assertTrue(RiskPolicy.shouldReject(0, 0, 75, 2, true));
    }
}

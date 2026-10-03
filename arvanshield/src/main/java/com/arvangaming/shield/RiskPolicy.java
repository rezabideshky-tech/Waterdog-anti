package com.arvangaming.shield;

/** Centralizes the fail-safe rule for converting evidence into a rejection. */
final class RiskPolicy {
    private RiskPolicy() {
    }

    static boolean shouldReject(int score, int independentSignals, int threshold,
                                int minimumIndependentSignals, boolean hardReject) {
        if (hardReject) {
            return true;
        }
        return score >= threshold && independentSignals >= minimumIndependentSignals;
    }

    static boolean shouldShedNewLogins(boolean attackMode, boolean explicitlyEnabled, boolean sustainedPressure) {
        return attackMode && explicitlyEnabled && sustainedPressure;
    }
}

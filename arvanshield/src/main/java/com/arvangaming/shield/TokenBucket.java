package com.arvangaming.shield;

import java.util.concurrent.atomic.AtomicReference;

/** Lock-free token bucket; time is supplied by the caller for deterministic tests. */
final class TokenBucket {
    private final AtomicReference<State> state;

    TokenBucket(int capacity, long now) {
        this.state = new AtomicReference<>(new State(capacity, now, now));
    }

    boolean tryConsume(int capacity, double refillPerSecond, long now) {
        while (true) {
            State current = this.state.get();
            // Event timestamps can be captured on different threads before CAS retries. Never let
            // a stale timestamp move refill/access time backwards and create extra tokens later.
            long effectiveNow = Math.max(now, current.lastRefillNanos());
            long effectiveAccess = Math.max(now, current.lastAccessNanos());
            long elapsedNanos = effectiveNow - current.lastRefillNanos();
            double elapsedSeconds = elapsedNanos / 1_000_000_000.0;
            double available = Math.min(capacity,
                    current.tokens() + elapsedSeconds * Math.max(0.0, refillPerSecond));
            boolean allowed = available >= 1.0;
            double remaining = allowed ? available - 1.0 : available;
            State updated = new State(remaining, effectiveNow, effectiveAccess);
            if (this.state.compareAndSet(current, updated)) {
                return allowed;
            }
        }
    }

    long lastAccessNanos() {
        return this.state.get().lastAccessNanos();
    }

    private record State(double tokens, long lastRefillNanos, long lastAccessNanos) {
    }
}

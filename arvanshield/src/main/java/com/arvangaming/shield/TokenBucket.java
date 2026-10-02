package com.arvangaming.shield;

/** Small synchronized token bucket; time is supplied by the caller for deterministic testing. */
final class TokenBucket {
    private double tokens;
    private long lastRefillNanos;
    private volatile long lastAccessNanos;

    TokenBucket(int capacity, long now) {
        this.tokens = capacity;
        this.lastRefillNanos = now;
        this.lastAccessNanos = now;
    }

    synchronized boolean tryConsume(int capacity, double refillPerSecond, long now) {
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

    long lastAccessNanos() {
        return this.lastAccessNanos;
    }
}

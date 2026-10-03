package com.arvangaming.shield;

import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

class TokenBucketTest {
    private static final long SECOND = 1_000_000_000L;

    @Test
    void enforcesConfiguredBurst() {
        TokenBucket bucket = new TokenBucket(2, SECOND);

        assertTrue(bucket.tryConsume(2, 0.0, SECOND));
        assertTrue(bucket.tryConsume(2, 0.0, SECOND));
        assertFalse(bucket.tryConsume(2, 0.0, SECOND));
    }

    @Test
    void refillsFractionallyOverTime() {
        TokenBucket bucket = new TokenBucket(2, SECOND);
        assertTrue(bucket.tryConsume(2, 0.0, SECOND));
        assertTrue(bucket.tryConsume(2, 0.0, SECOND));

        long halfSecondLater = SECOND + SECOND / 2;
        assertTrue(bucket.tryConsume(2, 2.0, halfSecondLater));
        assertFalse(bucket.tryConsume(2, 2.0, halfSecondLater));
    }

    @Test
    void capacityDecreaseClampsStoredTokens() {
        TokenBucket bucket = new TokenBucket(5, SECOND);

        assertTrue(bucket.tryConsume(1, 0.0, SECOND));
        assertFalse(bucket.tryConsume(1, 0.0, SECOND));
    }

    @Test
    void concurrentConsumersCannotExceedTheBurstCapacity() {
        TokenBucket bucket = new TokenBucket(25, SECOND);
        long accepted = java.util.stream.IntStream.range(0, 10_000)
                .parallel()
                .filter(ignored -> bucket.tryConsume(25, 0.0, SECOND))
                .count();

        assertEquals(25L, accepted);
    }

    @Test
    void recordsLastAccessForIdleCleanup() {
        TokenBucket bucket = new TokenBucket(1, SECOND);
        long access = SECOND + 25;

        bucket.tryConsume(1, 0.0, access);

        assertEquals(access, bucket.lastAccessNanos());
    }
}

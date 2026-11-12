/**
 * Minimal in-memory fixed window rate limiter. Per process (and thus per
 * replica): the actual limit of a deployment is multiplied by its number of
 * replicas.
 */
type Bucket = {count: number; resetAt: number};

export type RateLimitResult = {
    allowed: boolean;
    /** Seconds to wait before retrying, when not allowed */
    retryAfter: number;
};

export type FixedWindowLimiter = {
    /** Counts a hit for `key`, unless its limit is already reached */
    consume: (key: string, now?: number) => RateLimitResult;
    reset: () => void;
};

export function createFixedWindowLimiter({
    max,
    windowMs,
}: {
    /** Hits allowed per key and per window */
    max: number;
    windowMs: number;
}): FixedWindowLimiter {
    // Every window has the same length and a bucket is (re)inserted at the
    // end when it opens: the map is ordered by expiry, the expired buckets are
    // at its head. A clock going backwards only delays their eviction.
    const buckets = new Map<string, Bucket>();

    return {
        consume(key, now = Date.now()) {
            for (const [k, bucket] of buckets) {
                if (bucket.resetAt > now) {
                    break;
                }
                buckets.delete(k);
            }

            const bucket = buckets.get(key);
            if (!bucket || bucket.resetAt <= now) {
                buckets.delete(key);
                buckets.set(key, {count: 1, resetAt: now + windowMs});

                return {allowed: true, retryAfter: 0};
            }
            if (bucket.count >= max) {
                return {
                    allowed: false,
                    retryAfter: Math.ceil((bucket.resetAt - now) / 1000),
                };
            }
            bucket.count += 1;

            return {allowed: true, retryAfter: 0};
        },
        reset() {
            buckets.clear();
        },
    };
}

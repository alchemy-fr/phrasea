/**
 * Minimal in-memory rate limiter for ticket creation. Per process (and thus
 * per replica), which is enough to stop an accidental flood of JIRA issues.
 */
type Bucket = {count: number; resetAt: number};

const buckets = new Map<string, Bucket>();

export const ticketRateLimit = {
    max: 5,
    windowMs: 10 * 60 * 1000,
};

export type RateLimitResult = {
    allowed: boolean;
    /** Seconds to wait before retrying, when not allowed */
    retryAfter: number;
};

export function consumeRateLimit(
    key: string,
    now = Date.now(),
    {max, windowMs} = ticketRateLimit
): RateLimitResult {
    for (const [k, bucket] of buckets) {
        if (bucket.resetAt <= now) {
            buckets.delete(k);
        }
    }

    const bucket = buckets.get(key);
    if (!bucket) {
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
}

export function resetRateLimits(): void {
    buckets.clear();
}

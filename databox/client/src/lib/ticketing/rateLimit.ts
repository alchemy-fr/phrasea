/**
 * Rate limit of ticket creation, per user. In memory and per process (and
 * thus per replica), which is enough to stop an accidental flood of JIRA
 * issues.
 */
import {
    createFixedWindowLimiter,
    type RateLimitResult,
} from '@alchemy/next-core/server/rateLimit';

export type {RateLimitResult};

export const ticketRateLimit = {
    max: 5,
    windowMs: 10 * 60 * 1000,
};

const limiter = createFixedWindowLimiter(ticketRateLimit);

export function consumeRateLimit(
    key: string,
    now = Date.now()
): RateLimitResult {
    return limiter.consume(key, now);
}

export function resetRateLimits(): void {
    limiter.reset();
}

import {describe, expect, it} from 'vitest';
import {createFixedWindowLimiter} from './rateLimit';

describe('createFixedWindowLimiter', () => {
    const now = 1_000_000;

    it('allows max hits per key and per window', () => {
        const limiter = createFixedWindowLimiter({max: 3, windowMs: 60_000});
        for (let i = 0; i < 3; i++) {
            expect(limiter.consume('a', now + i)).toEqual({
                allowed: true,
                retryAfter: 0,
            });
        }

        expect(limiter.consume('a', now + 30_000)).toEqual({
            allowed: false,
            retryAfter: 30,
        });
        expect(limiter.consume('b', now + 30_000).allowed).toBe(true);
    });

    it('opens a new window once the previous one is over', () => {
        const limiter = createFixedWindowLimiter({max: 1, windowMs: 60_000});
        expect(limiter.consume('a', now).allowed).toBe(true);
        expect(limiter.consume('a', now + 59_999).allowed).toBe(false);
        expect(limiter.consume('a', now + 60_000).allowed).toBe(true);
        expect(limiter.consume('a', now + 60_001).allowed).toBe(false);
    });

    it('reopens an expired window that is not at the head of the map', () => {
        const limiter = createFixedWindowLimiter({max: 1, windowMs: 60_000});
        limiter.consume('a', now);
        limiter.consume('b', now - 1_000);

        expect(limiter.consume('b', now + 59_500).allowed).toBe(true);
        expect(limiter.consume('a', now + 59_500).allowed).toBe(false);
    });

    it('rounds the retry delay up to the second', () => {
        const limiter = createFixedWindowLimiter({max: 1, windowMs: 60_000});
        limiter.consume('a', now);

        expect(limiter.consume('a', now + 59_999).retryAfter).toBe(1);
    });

    it('keeps the limiters apart', () => {
        const first = createFixedWindowLimiter({max: 1, windowMs: 60_000});
        const second = createFixedWindowLimiter({max: 1, windowMs: 60_000});
        first.consume('a', now);

        expect(second.consume('a', now).allowed).toBe(true);
    });

    it('forgets every key on reset', () => {
        const limiter = createFixedWindowLimiter({max: 1, windowMs: 60_000});
        limiter.consume('a', now);
        limiter.reset();

        expect(limiter.consume('a', now).allowed).toBe(true);
    });
});

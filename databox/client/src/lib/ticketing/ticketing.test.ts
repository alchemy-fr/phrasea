import {describe, expect, it, beforeEach} from 'vitest';
import type {TicketPageContext} from '@/features/ticketing/types';
import {parseTicketPayload, TicketValidationError} from './payload';
import {buildAdfDescription, buildWikiDescription} from './description';
import {consumeRateLimit, resetRateLimits} from './rateLimit';

const page: TicketPageContext = {
    url: 'https://databox.example.com/assets?q=cat',
    path: '/assets?q=cat',
    title: 'Assets',
    locale: 'fr',
    timezone: 'Europe/Paris',
    viewport: {width: 1440, height: 900},
    screen: {width: 2560, height: 1440, pixelRatio: 2},
    userAgent: 'Mozilla/5.0',
    capturedAt: '2026-09-15T08:00:00.000Z',
};

const session = {
    userId: 'u-1',
    username: 'jdoe',
    email: 'jdoe@example.com',
    roles: ['user', 'databox-admin'],
    groups: [],
};

const validBody = {
    kind: 'bug',
    summary: '  Search is broken  ',
    description: 'It returns nothing',
    page,
};

describe('parseTicketPayload', () => {
    it('trims and keeps only known fields', () => {
        const payload = parseTicketPayload({
            ...validBody,
            evil: 'ignored',
            page: {...page, evil: 'ignored'},
        });

        expect(payload.summary).toBe('Search is broken');
        expect(payload.kind).toBe('bug');
        expect(payload.page.url).toBe(page.url);
        expect(payload).not.toHaveProperty('evil');
        expect(payload.page).not.toHaveProperty('evil');
    });

    it('rejects an empty summary, an unknown kind and a missing url', () => {
        expect(() =>
            parseTicketPayload({...validBody, summary: '   '})
        ).toThrow(TicketValidationError);
        expect(() => parseTicketPayload({...validBody, kind: 'other'})).toThrow(
            TicketValidationError
        );
        expect(() => parseTicketPayload({...validBody, page: {}})).toThrow(
            TicketValidationError
        );
    });

    it('caps long strings and the number of collected errors', () => {
        const payload = parseTicketPayload({
            ...validBody,
            description: 'x'.repeat(20_000),
            page: {
                ...page,
                errors: Array.from({length: 30}, (_, i) => ({
                    at: '2026-09-15T08:00:00.000Z',
                    message: `boom ${i}`,
                })),
            },
        });

        expect(payload.description).toHaveLength(10_000);
        expect(payload.page.errors).toHaveLength(10);
        expect(payload.page.errors?.[0].message).toBe('boom 20');
    });

    it('only accepts image data urls as screenshot', () => {
        expect(
            parseTicketPayload({
                ...validBody,
                screenshot: 'data:image/png;base64,AAAA',
            }).screenshot
        ).toBe('data:image/png;base64,AAAA');

        expect(() =>
            parseTicketPayload({
                ...validBody,
                screenshot: 'https://evil.example.com/x.png',
            })
        ).toThrow(TicketValidationError);

        expect(() =>
            parseTicketPayload({
                ...validBody,
                screenshot: `data:image/png;base64,${'A'.repeat(10 * 1024 * 1024)}`,
            })
        ).toThrow(/too large/);
    });
});

describe('description', () => {
    it('builds an ADF document carrying the page and the session', () => {
        const doc = buildAdfDescription({
            description: 'First paragraph\n\nSecond paragraph',
            page,
            session,
        });

        expect(doc.type).toBe('doc');
        const text = JSON.stringify(doc);
        expect(text).toContain('First paragraph');
        expect(text).toContain('Second paragraph');
        expect(text).toContain(page.url);
        expect(text).toContain('jdoe@example.com');
        expect(text).toContain('databox-admin');
        // No console error section when there is none
        expect(text).not.toContain('Console errors');
    });

    it('adds the console errors when present', () => {
        const doc = buildAdfDescription({
            description: 'nope',
            page: {
                ...page,
                errors: [{at: '2026-09-15T08:00:00.000Z', message: 'Boom'}],
            },
            session,
        });

        expect(JSON.stringify(doc)).toContain('Console errors');
    });

    it('builds wiki markup for JIRA Server', () => {
        const text = buildWikiDescription({
            description: 'Broken',
            page,
            session,
        });

        expect(text).toContain('h3. Page');
        expect(text).toContain(page.url);
        expect(text).toContain('jdoe');
    });
});

describe('consumeRateLimit', () => {
    beforeEach(resetRateLimits);

    it('allows up to the limit then rejects within the window', () => {
        const now = 1_000_000;
        for (let i = 0; i < 5; i++) {
            expect(consumeRateLimit('u-1', now).allowed).toBe(true);
        }
        const blocked = consumeRateLimit('u-1', now);
        expect(blocked.allowed).toBe(false);
        expect(blocked.retryAfter).toBeGreaterThan(0);

        // Another user is not affected
        expect(consumeRateLimit('u-2', now).allowed).toBe(true);

        // ... and the window eventually resets
        expect(consumeRateLimit('u-1', now + 11 * 60 * 1000).allowed).toBe(
            true
        );
    });
});

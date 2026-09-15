// @vitest-environment node
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {resetRateLimits} from '@/lib/ticketing/rateLimit';
import type {TicketPageContext} from '@/features/ticketing/types';

const mocks = vi.hoisted(() => ({
    enabled: true,
    resolveSession: vi.fn(),
    createJiraIssue: vi.fn(),
    attachScreenshot: vi.fn(),
}));

vi.mock('@/lib/config/server', () => ({
    getServerConfig: () => ({ticketing: {enabled: mocks.enabled}}),
}));

vi.mock('@/lib/ticketing/session', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/ticketing/session')>()),
    resolveSession: mocks.resolveSession,
}));

vi.mock('@/lib/ticketing/jira', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/ticketing/jira')>()),
    createJiraIssue: mocks.createJiraIssue,
    attachScreenshot: mocks.attachScreenshot,
}));

const {POST} = await import('./route');

const page: TicketPageContext = {
    url: 'https://databox.example.com/assets',
    path: '/assets',
    capturedAt: '2026-09-15T08:00:00.000Z',
};

const session = {userId: 'u-1', username: 'jdoe'};

function post(body: unknown, token: string | null = 'token'): Request {
    return new Request('https://databox-next.example.com/api/ticketing', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            ...(token ? {Authorization: `Bearer ${token}`} : {}),
        },
        body: JSON.stringify(body),
    });
}

const validBody = {
    kind: 'bug',
    summary: 'Search is broken',
    description: 'It returns nothing',
    page,
};

describe('POST /api/ticketing', () => {
    beforeEach(() => {
        resetRateLimits();
        mocks.enabled = true;
        mocks.resolveSession.mockReset().mockResolvedValue(session);
        mocks.createJiraIssue.mockReset().mockResolvedValue({
            key: 'DAM-1',
            url: 'https://jira/browse/DAM-1',
        });
        mocks.attachScreenshot.mockReset().mockResolvedValue(undefined);
        vi.stubEnv('DATABOX_TICKETING_JIRA_URL', 'https://jira.example.com');
        vi.stubEnv('DATABOX_TICKETING_JIRA_API_TOKEN', 'secret');
        vi.stubEnv('DATABOX_TICKETING_JIRA_PROJECT_KEY', 'DAM');
        vi.spyOn(console, 'error').mockImplementation(() => undefined);
    });

    afterEach(() => {
        vi.unstubAllEnvs();
        vi.restoreAllMocks();
    });

    it('is not exposed when the module is disabled', async () => {
        mocks.enabled = false;
        const response = await POST(post(validBody));

        expect(response.status).toBe(404);
        expect(mocks.createJiraIssue).not.toHaveBeenCalled();
    });

    it('fails when JIRA is not configured', async () => {
        vi.stubEnv('DATABOX_TICKETING_JIRA_URL', '');
        const response = await POST(post(validBody));

        expect(response.status).toBe(503);
    });

    it('requires an access token', async () => {
        const response = await POST(post(validBody, null));

        expect(response.status).toBe(401);
        expect(mocks.resolveSession).not.toHaveBeenCalled();
    });

    it('rejects a token Keycloak does not know', async () => {
        const {InvalidTokenError} = await import('@/lib/ticketing/session');
        mocks.resolveSession.mockRejectedValue(new InvalidTokenError());
        const response = await POST(post(validBody));

        expect(response.status).toBe(401);
        expect(mocks.createJiraIssue).not.toHaveBeenCalled();
    });

    it('rejects an invalid payload', async () => {
        const response = await POST(post({...validBody, summary: ''}));

        expect(response.status).toBe(400);
        expect(mocks.createJiraIssue).not.toHaveBeenCalled();
    });

    it('creates the issue with the server side session', async () => {
        const response = await POST(
            post({...validBody, session: {userId: 'forged'}})
        );

        expect(response.status).toBe(200);
        await expect(response.json()).resolves.toEqual({
            key: 'DAM-1',
            url: 'https://jira/browse/DAM-1',
            screenshotAttached: false,
        });

        const [config, payload, usedSession] =
            mocks.createJiraIssue.mock.calls[0];
        expect(config.projectKey).toBe('DAM');
        expect(payload.summary).toBe('Search is broken');
        expect(payload.page.url).toBe(page.url);
        // The identity always comes from the verified token
        expect(usedSession).toBe(session);
        expect(mocks.attachScreenshot).not.toHaveBeenCalled();
    });

    it('attaches the screenshot but survives a failed upload', async () => {
        mocks.attachScreenshot.mockRejectedValue(new Error('nope'));
        const response = await POST(
            post({...validBody, screenshot: 'data:image/png;base64,AAAA'})
        );

        expect(response.status).toBe(200);
        await expect(response.json()).resolves.toMatchObject({
            key: 'DAM-1',
            screenshotAttached: false,
        });
        expect(mocks.attachScreenshot).toHaveBeenCalledWith(
            expect.anything(),
            'DAM-1',
            'data:image/png;base64,AAAA'
        );
    });

    it('reports a JIRA failure', async () => {
        const {JiraError} = await import('@/lib/ticketing/jira');
        mocks.createJiraIssue.mockRejectedValue(
            new JiraError('summary: required', 400)
        );
        const response = await POST(post(validBody));

        expect(response.status).toBe(502);
        await expect(response.json()).resolves.toMatchObject({
            message: expect.stringContaining('summary: required'),
        });
    });

    it('rate limits a flooding user', async () => {
        for (let i = 0; i < 5; i++) {
            expect((await POST(post(validBody))).status).toBe(200);
        }
        const response = await POST(post(validBody));

        expect(response.status).toBe(429);
        expect(response.headers.get('Retry-After')).toBeTruthy();
        expect(mocks.createJiraIssue).toHaveBeenCalledTimes(5);
    });
});

import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {OidcClient, SILENT_LOGIN_TIMEOUT_MS, type AuthEvent} from './oidc';

// Node ships its own (broken, file-backed) localStorage that shadows jsdom's
const storage = new Map<string, string>();
Object.defineProperty(window, 'localStorage', {
    configurable: true,
    value: {
        getItem: (k: string) => storage.get(k) ?? null,
        setItem: (k: string, v: string) => storage.set(k, v),
        removeItem: (k: string) => storage.delete(k),
        clear: () => storage.clear(),
    },
});

const issuerUrl = 'https://keycloak.phrasea.test/realms/phrasea';
const tokenEndpoint = `${issuerUrl}/protocol/openid-connect/token`;
const origin = window.location.origin;
const silentRedirectUri = `${origin}/silent-check-sso.html`;

function base64url(input: string | ArrayBuffer): string {
    const str =
        typeof input === 'string'
            ? input
            : String.fromCharCode(...new Uint8Array(input));

    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function jwt(claims: Record<string, unknown>): string {
    return [
        base64url(JSON.stringify({alg: 'none'})),
        base64url(JSON.stringify(claims)),
        'signature',
    ].join('.');
}

const accessToken = jwt({sub: 'alice-id', preferred_username: 'alice'});

function tokenResponse(
    status = 200,
    body: Record<string, unknown> = {
        access_token: accessToken,
        id_token: 'id-token',
        refresh_token: 'refresh-token',
        expires_in: 300,
        refresh_expires_in: 1800,
    }
) {
    return {ok: status < 400, status, json: async () => body};
}

const fetchMock = vi.fn();

function createClient(): OidcClient {
    return new OidcClient({
        issuerUrl,
        clientId: 'databox-app',
        redirectUri: `${origin}/auth/callback`,
        postLogoutRedirectUri: `${origin}/`,
        silentRedirectUri,
        idpHint: 'external-idp',
    });
}

async function findIframe(): Promise<HTMLIFrameElement> {
    return vi.waitFor(() => {
        const iframe = document.querySelector('iframe');
        if (!iframe) {
            throw new Error('No iframe yet');
        }

        return iframe;
    });
}

function authParams(iframe: HTMLIFrameElement): URLSearchParams {
    return new URL(iframe.src).searchParams;
}

function postFromIframe(
    iframe: HTMLIFrameElement,
    fragment: string,
    {
        from = origin,
        source = iframe.contentWindow,
    }: {from?: string; source?: Window | null} = {}
): void {
    window.dispatchEvent(
        new MessageEvent('message', {
            data: `${silentRedirectUri}#${fragment}`,
            origin: from,
            source,
        })
    );
}

function fetchBody(call = 0): URLSearchParams {
    return fetchMock.mock.calls[call][1].body as URLSearchParams;
}

beforeEach(() => {
    storage.clear();
    document.body.innerHTML = '';
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('OidcClient.silentLogin', () => {
    it('signs in from the Keycloak SSO session', async () => {
        fetchMock.mockResolvedValue(tokenResponse());
        const client = createClient();
        const events: AuthEvent[] = [];
        client.subscribe(e => events.push(e));

        const promise = client.silentLogin();
        const iframe = await findIframe();

        expect(iframe.style.display).toBe('none');
        expect(
            iframe.src.startsWith(`${issuerUrl}/protocol/openid-connect/auth?`)
        ).toBe(true);
        const params = authParams(iframe);
        expect(params.get('prompt')).toBe('none');
        expect(params.get('response_mode')).toBe('fragment');
        expect(params.get('response_type')).toBe('code');
        expect(params.get('client_id')).toBe('databox-app');
        expect(params.get('redirect_uri')).toBe(silentRedirectUri);
        expect(params.get('code_challenge_method')).toBe('S256');
        expect(params.has('kc_idp_hint')).toBe(false);

        postFromIframe(iframe, `state=${params.get('state')}&code=abc`);

        const user = await promise;
        expect(user?.id).toBe('alice-id');
        expect(user?.username).toBe('alice');
        expect(client.getUser()?.id).toBe('alice-id');

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0][0]).toBe(tokenEndpoint);
        const body = fetchBody();
        expect(body.get('grant_type')).toBe('authorization_code');
        expect(body.get('code')).toBe('abc');
        expect(body.get('redirect_uri')).toBe(silentRedirectUri);
        const digest = await crypto.subtle.digest(
            'SHA-256',
            new TextEncoder().encode(body.get('code_verifier')!)
        );
        expect(base64url(digest)).toBe(params.get('code_challenge'));

        expect(JSON.parse(storage.get('dbx.auth')!).refreshToken).toBe(
            'refresh-token'
        );
        expect(events).toEqual(['login']);
        expect(document.querySelector('iframe')).toBeNull();
    });

    it('stays anonymous without a Keycloak SSO session', async () => {
        const client = createClient();

        const promise = client.silentLogin();
        const iframe = await findIframe();
        postFromIframe(
            iframe,
            `error=login_required&state=${authParams(iframe).get('state')}`
        );

        expect(await promise).toBeUndefined();
        expect(fetchMock).not.toHaveBeenCalled();
        expect(client.getUser()).toBeUndefined();
        expect(document.querySelector('iframe')).toBeNull();
    });

    it('ignores messages from another origin or another frame', async () => {
        fetchMock.mockResolvedValue(tokenResponse());
        const client = createClient();

        const promise = client.silentLogin();
        const iframe = await findIframe();
        const state = authParams(iframe).get('state');

        postFromIframe(iframe, `state=${state}&code=evil`, {
            from: 'https://evil.test',
        });
        postFromIframe(iframe, `state=${state}&code=evil`, {source: window});
        await Promise.resolve();
        expect(fetchMock).not.toHaveBeenCalled();
        expect(document.querySelector('iframe')).toBe(iframe);

        postFromIframe(iframe, `state=${state}&code=abc`);

        expect((await promise)?.id).toBe('alice-id');
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchBody().get('code')).toBe('abc');
    });

    it('rejects a response with another state', async () => {
        const client = createClient();

        const promise = client.silentLogin();
        const iframe = await findIframe();
        postFromIframe(iframe, 'state=forged&code=abc');

        expect(await promise).toBeUndefined();
        expect(fetchMock).not.toHaveBeenCalled();
        expect(document.querySelector('iframe')).toBeNull();
    });

    it('gives up after the timeout', async () => {
        vi.useFakeTimers({toFake: ['setTimeout', 'clearTimeout']});
        const client = createClient();

        const promise = client.silentLogin();
        await findIframe();
        await vi.advanceTimersByTimeAsync(SILENT_LOGIN_TIMEOUT_MS);

        expect(await promise).toBeUndefined();
        expect(fetchMock).not.toHaveBeenCalled();
        expect(document.querySelector('iframe')).toBeNull();
    });

    it('stays anonymous when the code exchange fails', async () => {
        fetchMock.mockResolvedValue(
            tokenResponse(400, {error: 'invalid_grant'})
        );
        const client = createClient();
        const events: AuthEvent[] = [];
        client.subscribe(e => events.push(e));

        const promise = client.silentLogin();
        const iframe = await findIframe();
        postFromIframe(
            iframe,
            `state=${authParams(iframe).get('state')}&code=abc`
        );

        expect(await promise).toBeUndefined();
        expect(client.getUser()).toBeUndefined();
        expect(storage.has('dbx.auth')).toBe(false);
        expect(events).toEqual([]);
        expect(document.querySelector('iframe')).toBeNull();
    });

    it('does nothing without a silent redirect URI', async () => {
        const client = new OidcClient({
            issuerUrl,
            clientId: 'databox-app',
            redirectUri: `${origin}/auth/callback`,
            postLogoutRedirectUri: `${origin}/`,
        });

        expect(await client.silentLogin()).toBeUndefined();
        expect(document.querySelector('iframe')).toBeNull();
    });
});

describe('OidcClient.init', () => {
    it('restores the stored session without the iframe', async () => {
        storage.set('dbx.auth', JSON.stringify({refreshToken: 'stored'}));
        fetchMock.mockResolvedValue(tokenResponse());
        const client = createClient();

        expect((await client.init({silent: true}))?.id).toBe('alice-id');
        expect(fetchBody().get('grant_type')).toBe('refresh_token');
        expect(fetchBody().get('refresh_token')).toBe('stored');
        expect(document.querySelector('iframe')).toBeNull();
    });

    it('falls back to the SSO session when the stored one expired', async () => {
        storage.set('dbx.auth', JSON.stringify({refreshToken: 'stored'}));
        fetchMock
            .mockResolvedValueOnce(tokenResponse(400, {error: 'invalid_grant'}))
            .mockResolvedValueOnce(tokenResponse());
        const client = createClient();
        const events: AuthEvent[] = [];
        client.subscribe(e => events.push(e));

        const promise = client.init({silent: true});
        const iframe = await findIframe();
        postFromIframe(
            iframe,
            `state=${authParams(iframe).get('state')}&code=abc`
        );

        expect((await promise)?.id).toBe('alice-id');
        expect(events).toEqual(['expired', 'login']);
    });

    it('checks the SSO session without a stored one', async () => {
        const client = createClient();

        const promise = client.init({silent: true});
        const iframe = await findIframe();
        postFromIframe(
            iframe,
            `error=login_required&state=${authParams(iframe).get('state')}`
        );

        expect(await promise).toBeUndefined();
    });

    it('stays anonymous without the silent option', async () => {
        const client = createClient();

        expect(await client.init()).toBeUndefined();
        expect(fetchMock).not.toHaveBeenCalled();
        expect(document.querySelector('iframe')).toBeNull();
    });

    it('shares one silent sign-in between concurrent calls', async () => {
        const client = createClient();

        const first = client.init({silent: true});
        const second = client.init({silent: true});
        const iframe = await findIframe();
        expect(document.querySelectorAll('iframe')).toHaveLength(1);

        postFromIframe(
            iframe,
            `error=login_required&state=${authParams(iframe).get('state')}`
        );

        expect(await first).toBeUndefined();
        expect(await second).toBeUndefined();
        expect(document.querySelector('iframe')).toBeNull();
    });
});

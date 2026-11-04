/**
 * Minimal OpenID Connect client (Authorization Code + PKCE) for Keycloak.
 *
 * - The access token only lives in memory.
 * - The refresh token is persisted in localStorage so the session survives a
 *   reload; it is refreshed proactively before the access token expires.
 * - Without a stored session, the Keycloak SSO session (opened from another
 *   Phrasea app) is picked up silently at startup: `prompt=none` authorization
 *   in a hidden iframe.
 * - Consumers subscribe to auth events (login / logout / expired).
 */

export type AuthUser = {
    id: string;
    username: string;
    email?: string;
    name?: string;
    roles: string[];
    groups: string[];
    /** Granted by the Keycloak `impersonation` role of `realm-management` */
    canImpersonate?: boolean;
};

export type Tokens = {
    accessToken: string;
    idToken?: string;
    refreshToken?: string;
    expiresAt: number; // epoch seconds
    refreshExpiresAt?: number; // epoch seconds
};

export type AuthEvent = 'login' | 'logout' | 'expired' | 'refresh';
type Listener = (event: AuthEvent) => void;

export type OidcOptions = {
    issuerUrl: string; // {keycloakUrl}/realms/{realm}
    clientId: string;
    redirectUri: string; // absolute URL of the callback page
    /** Absolute URL of the page posting its URL to the parent frame */
    silentRedirectUri?: string;
    postLogoutRedirectUri: string;
    scope?: string;
    idpHint?: string;
    storageKey?: string;
};

type StoredState = {
    verifier: string;
    redirectTo: string;
    createdAt: number;
};

const textEncoder = new TextEncoder();

/** Delay after which the silent sign-in gives up and leaves the app anonymous */
export const SILENT_LOGIN_TIMEOUT_MS = 5000;

function base64url(bytes: ArrayBuffer | Uint8Array): string {
    const arr = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    let str = '';
    arr.forEach(b => (str += String.fromCharCode(b)));

    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function randomString(length = 64): string {
    const bytes = new Uint8Array(length);
    crypto.getRandomValues(bytes);

    return base64url(bytes).slice(0, length);
}

async function sha256(input: string): Promise<string> {
    const digest = await crypto.subtle.digest(
        'SHA-256',
        textEncoder.encode(input)
    );

    return base64url(digest);
}

export function decodeJwt<T = Record<string, unknown>>(token: string): T {
    const [, payload] = token.split('.');
    const json = atob(payload.replace(/-/g, '+').replace(/_/g, '/'));

    return JSON.parse(
        decodeURIComponent(
            json
                .split('')
                .map(c => `%${c.charCodeAt(0).toString(16).padStart(2, '0')}`)
                .join('')
        )
    ) as T;
}

function now(): number {
    return Math.floor(Date.now() / 1000);
}

export class OidcError extends Error {
    constructor(
        message: string,
        public readonly code?: string
    ) {
        super(message);
    }
}

export class OidcClient {
    private tokens: Tokens | undefined;
    private refreshPromise: Promise<Tokens | undefined> | undefined;
    private refreshTimer: ReturnType<typeof setTimeout> | undefined;
    private silentLoginPromise: Promise<AuthUser | undefined> | undefined;
    private readonly callbackPromises = new Map<string, Promise<string>>();
    private readonly listeners = new Set<Listener>();
    private readonly storageKey: string;

    constructor(private readonly options: OidcOptions) {
        this.storageKey = options.storageKey ?? 'dbx.auth';
    }

    // -- URLs ---------------------------------------------------------------

    private get authorizationEndpoint(): string {
        return `${this.options.issuerUrl}/protocol/openid-connect/auth`;
    }

    private get tokenEndpoint(): string {
        return `${this.options.issuerUrl}/protocol/openid-connect/token`;
    }

    private get logoutEndpoint(): string {
        return `${this.options.issuerUrl}/protocol/openid-connect/logout`;
    }

    public getAccountUrl(referrerUri: string): string {
        const sp = new URLSearchParams({
            referrer: this.options.clientId,
            referrer_uri: referrerUri,
        });

        return `${this.options.issuerUrl}/account/?${sp.toString()}`;
    }

    // -- Events -------------------------------------------------------------

    public subscribe(listener: Listener): () => void {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    }

    private emit(event: AuthEvent): void {
        this.listeners.forEach(l => l(event));
    }

    // -- Storage ------------------------------------------------------------

    private get storage(): Storage | undefined {
        try {
            return typeof window !== 'undefined'
                ? window.localStorage
                : undefined;
        } catch {
            return undefined;
        }
    }

    private persistRefreshToken(tokens: Tokens | undefined): void {
        const storage = this.storage;
        if (!storage) {
            return;
        }
        if (tokens?.refreshToken) {
            storage.setItem(
                this.storageKey,
                JSON.stringify({
                    refreshToken: tokens.refreshToken,
                    refreshExpiresAt: tokens.refreshExpiresAt,
                })
            );
        } else {
            storage.removeItem(this.storageKey);
        }
    }

    private readStoredRefreshToken():
        | {refreshToken: string; refreshExpiresAt?: number}
        | undefined {
        const raw = this.storage?.getItem(this.storageKey);
        if (!raw) {
            return undefined;
        }
        try {
            const data = JSON.parse(raw);
            if (data.refreshExpiresAt && data.refreshExpiresAt <= now() + 5) {
                this.storage?.removeItem(this.storageKey);

                return undefined;
            }

            return data;
        } catch {
            return undefined;
        }
    }

    public hasSession(): boolean {
        return !!this.tokens || !!this.readStoredRefreshToken();
    }

    // -- Tokens -------------------------------------------------------------

    public getTokens(): Tokens | undefined {
        return this.tokens;
    }

    public getUser(): AuthUser | undefined {
        if (!this.tokens) {
            return undefined;
        }
        const claims = decodeJwt<{
            sub: string;
            preferred_username?: string;
            email?: string;
            name?: string;
            groups?: string[];
            roles?: string[];
            realm_access?: {roles?: string[]};
            resource_access?: Record<string, {roles?: string[]} | undefined>;
        }>(this.tokens.accessToken);

        return {
            id: claims.sub,
            username: claims.preferred_username ?? claims.sub,
            email: claims.email,
            name: claims.name,
            roles: [
                ...new Set([
                    ...(claims.roles ?? []),
                    ...(claims.realm_access?.roles ?? []),
                ]),
            ],
            groups: claims.groups ?? [],
            canImpersonate:
                claims.resource_access?.['realm-management']?.roles?.includes(
                    'impersonation'
                ) ?? false,
        };
    }

    private setTokens(response: Record<string, any>): Tokens {
        const t = now();
        const tokens: Tokens = {
            accessToken: response.access_token,
            idToken: response.id_token,
            refreshToken: response.refresh_token,
            expiresAt: t + Number(response.expires_in ?? 60),
            refreshExpiresAt: response.refresh_expires_in
                ? t + Number(response.refresh_expires_in)
                : undefined,
        };
        this.tokens = tokens;
        this.persistRefreshToken(tokens);
        this.scheduleRefresh(tokens);

        return tokens;
    }

    private scheduleRefresh(tokens: Tokens): void {
        clearTimeout(this.refreshTimer);
        // refresh 30s before expiry (but at least in 5s)
        const delay = Math.max(5, tokens.expiresAt - now() - 30) * 1000;
        if (delay < 7 * 24 * 3600 * 1000) {
            this.refreshTimer = setTimeout(() => {
                if (typeof document === 'undefined' || !document.hidden) {
                    this.refresh().catch(() => undefined);
                }
            }, delay);
        }
    }

    /**
     * Returns a valid access token, refreshing it when needed.
     * Resolves to undefined when there is no session.
     */
    public async getAccessToken(): Promise<string | undefined> {
        if (this.tokens && this.tokens.expiresAt > now() + 10) {
            return this.tokens.accessToken;
        }

        const tokens = await this.refresh();

        return tokens?.accessToken;
    }

    /**
     * Restores the session from the persisted refresh token. When there is
     * none, or it can no longer be used, and `silent` is set, falls back to
     * the Keycloak SSO session through a silent sign-in.
     *
     * Never rejects: resolves to undefined when the user stays anonymous.
     */
    public async init({silent = false}: {silent?: boolean} = {}): Promise<
        AuthUser | undefined
    > {
        if (this.tokens) {
            return this.getUser();
        }
        if (this.readStoredRefreshToken()) {
            await this.refresh().catch(() => undefined);
            const user = this.getUser();
            if (user) {
                return user;
            }
        }

        return silent ? this.silentLogin() : undefined;
    }

    public async refresh(): Promise<Tokens | undefined> {
        if (this.refreshPromise) {
            return this.refreshPromise;
        }
        const refreshToken =
            this.tokens?.refreshToken ??
            this.readStoredRefreshToken()?.refreshToken;
        if (!refreshToken) {
            return undefined;
        }

        this.refreshPromise = (async () => {
            try {
                const res = await fetch(this.tokenEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        grant_type: 'refresh_token',
                        client_id: this.options.clientId,
                        refresh_token: refreshToken,
                    }),
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    if (res.status === 400 || res.status === 401) {
                        this.clear();
                        this.emit('expired');
                    }
                    throw new OidcError(
                        data.error_description ?? 'Unable to refresh token',
                        data.error
                    );
                }
                const tokens = this.setTokens(await res.json());
                this.emit('refresh');

                return tokens;
            } finally {
                this.refreshPromise = undefined;
            }
        })();

        return this.refreshPromise;
    }

    // -- Authorization code flow -------------------------------------------

    private authorizationParams(
        redirectUri: string,
        state: string,
        challenge: string
    ): URLSearchParams {
        return new URLSearchParams({
            response_type: 'code',
            client_id: this.options.clientId,
            redirect_uri: redirectUri,
            scope: this.options.scope ?? 'openid profile email',
            state,
            code_challenge: challenge,
            code_challenge_method: 'S256',
        });
    }

    public async login(redirectTo: string = '/'): Promise<void> {
        const verifier = randomString(96);
        const challenge = await sha256(verifier);
        const state = randomString(32);

        sessionStorage.setItem(
            `${this.storageKey}.state.${state}`,
            JSON.stringify({
                verifier,
                redirectTo,
                createdAt: Date.now(),
            } satisfies StoredState)
        );

        const params = this.authorizationParams(
            this.options.redirectUri,
            state,
            challenge
        );
        if (this.options.idpHint) {
            params.set('kc_idp_hint', this.options.idpHint);
        }

        // Keycloak is an external origin: a full navigation is required.
        // eslint-disable-next-line @next/next/no-location-assign-relative-destination
        window.location.assign(
            `${this.authorizationEndpoint}?${params.toString()}`
        );
    }

    /**
     * Signs in without user interaction when a Keycloak SSO session exists:
     * `prompt=none` authorization in a hidden iframe, whose redirection page
     * posts its URL back. Resolves to undefined whenever that is not possible
     * (no SSO session, third-party cookies blocked, timeout, any error).
     *
     * Concurrent calls share the same attempt: React may run the startup
     * effect twice (Strict Mode in development).
     */
    public silentLogin(): Promise<AuthUser | undefined> {
        if (!this.silentLoginPromise) {
            this.silentLoginPromise = this.doSilentLogin()
                .catch(() => undefined)
                .finally(() => {
                    this.silentLoginPromise = undefined;
                });
        }

        return this.silentLoginPromise;
    }

    private async doSilentLogin(): Promise<AuthUser | undefined> {
        const redirectUri = this.options.silentRedirectUri;
        if (
            typeof window === 'undefined' ||
            typeof document === 'undefined' ||
            !redirectUri
        ) {
            return undefined;
        }

        // Kept in memory: the response comes back to this very page.
        const verifier = randomString(96);
        const challenge = await sha256(verifier);
        const state = randomString(32);

        const params = this.authorizationParams(redirectUri, state, challenge);
        params.set('prompt', 'none');
        // The code stays in the fragment: it never reaches the Next server.
        params.set('response_mode', 'fragment');
        // No `kc_idp_hint`: it could send to the external IdP instead of
        // answering `login_required`.

        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.title = 'silent-check-sso';
        iframe.src = `${this.authorizationEndpoint}?${params.toString()}`;

        const controller = new AbortController();
        const timer = setTimeout(
            () => controller.abort(),
            SILENT_LOGIN_TIMEOUT_MS
        );
        try {
            const response = await this.waitForSilentResponse(
                iframe,
                controller.signal
            );
            const code = response?.get('code');
            if (
                !response ||
                response.get('state') !== state ||
                response.has('error') ||
                !code
            ) {
                return undefined;
            }

            const res = await fetch(this.tokenEndpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    grant_type: 'authorization_code',
                    client_id: this.options.clientId,
                    code,
                    redirect_uri: redirectUri,
                    code_verifier: verifier,
                }),
                signal: controller.signal,
            });
            if (!res.ok) {
                return undefined;
            }
            const json = await res.json();
            if (controller.signal.aborted) {
                return undefined;
            }
            this.setTokens(json);
            this.emit('login');

            return this.getUser();
        } finally {
            clearTimeout(timer);
            iframe.remove();
        }
    }

    /**
     * Appends the iframe and resolves to the parameters of the URL its
     * redirection page posts, or to undefined once `signal` is aborted.
     */
    private waitForSilentResponse(
        iframe: HTMLIFrameElement,
        signal: AbortSignal
    ): Promise<URLSearchParams | undefined> {
        return new Promise(resolve => {
            const done = (params: URLSearchParams | undefined) => {
                window.removeEventListener('message', onMessage);
                signal.removeEventListener('abort', onAbort);
                resolve(params);
            };
            const onAbort = () => done(undefined);
            const onMessage = (event: MessageEvent) => {
                if (
                    event.origin !== window.location.origin ||
                    !iframe.contentWindow ||
                    event.source !== iframe.contentWindow
                ) {
                    return;
                }
                try {
                    done(
                        new URLSearchParams(
                            new URL(String(event.data)).hash.slice(1)
                        )
                    );
                } catch {
                    done(undefined);
                }
            };

            window.addEventListener('message', onMessage);
            signal.addEventListener('abort', onAbort);
            document.body.appendChild(iframe);
        });
    }

    /**
     * Exchanges the authorization code. Returns the path to redirect to.
     *
     * Idempotent per `state`: React may run the callback effect twice (Strict
     * Mode in development), the second call must share the first exchange
     * instead of failing on an already consumed login state.
     */
    public handleCallback(searchParams: URLSearchParams): Promise<string> {
        const state = searchParams.get('state') ?? '';
        const pending = this.callbackPromises.get(state);
        if (pending) {
            return pending;
        }
        const promise = this.doHandleCallback(searchParams).finally(() => {
            // Keep the settled promise for a while: late duplicate calls get
            // the same outcome instead of an "invalid state" error.
            setTimeout(() => this.callbackPromises.delete(state), 60000);
        });
        this.callbackPromises.set(state, promise);

        return promise;
    }

    private async doHandleCallback(
        searchParams: URLSearchParams
    ): Promise<string> {
        const error = searchParams.get('error');
        if (error) {
            throw new OidcError(
                searchParams.get('error_description') ?? error,
                error
            );
        }
        const code = searchParams.get('code');
        const state = searchParams.get('state');
        if (!code || !state) {
            throw new OidcError('Missing authorization code');
        }
        const key = `${this.storageKey}.state.${state}`;
        const raw = sessionStorage.getItem(key);
        if (!raw) {
            throw new OidcError(
                'Invalid or expired login state',
                'invalid_state'
            );
        }
        const stored: StoredState = JSON.parse(raw);

        const res = await fetch(this.tokenEndpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                grant_type: 'authorization_code',
                client_id: this.options.clientId,
                code,
                redirect_uri: this.options.redirectUri,
                code_verifier: stored.verifier,
            }),
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new OidcError(
                data.error_description ?? 'Login failed',
                data.error
            );
        }
        this.setTokens(await res.json());
        sessionStorage.removeItem(key);
        this.emit('login');

        return stored.redirectTo || '/';
    }

    // -- Logout -------------------------------------------------------------

    private clear(): void {
        clearTimeout(this.refreshTimer);
        this.tokens = undefined;
        this.persistRefreshToken(undefined);
    }

    public async logout({
        redirect = true,
    }: {redirect?: boolean} = {}): Promise<void> {
        const idToken = this.tokens?.idToken;
        this.clear();
        this.emit('logout');

        if (redirect) {
            const params = new URLSearchParams({
                client_id: this.options.clientId,
                post_logout_redirect_uri: this.options.postLogoutRedirectUri,
            });
            if (idToken) {
                params.set('id_token_hint', idToken);
            }
            // eslint-disable-next-line @next/next/no-location-assign-relative-destination
            window.location.assign(
                `${this.logoutEndpoint}?${params.toString()}`
            );
        }
    }
}

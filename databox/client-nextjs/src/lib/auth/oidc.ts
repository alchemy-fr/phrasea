/**
 * Minimal OpenID Connect client (Authorization Code + PKCE) for Keycloak.
 *
 * - The access token only lives in memory.
 * - The refresh token is persisted in localStorage so the session survives a
 *   reload; it is refreshed proactively before the access token expires.
 * - Consumers subscribe to auth events (login / logout / expired).
 */

export type AuthUser = {
    id: string;
    username: string;
    email?: string;
    name?: string;
    roles: string[];
    groups: string[];
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
     * Restores the session from the persisted refresh token.
     */
    public async init(): Promise<AuthUser | undefined> {
        if (this.tokens) {
            return this.getUser();
        }
        if (!this.readStoredRefreshToken()) {
            return undefined;
        }
        await this.refresh();

        return this.getUser();
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

        const params = new URLSearchParams({
            response_type: 'code',
            client_id: this.options.clientId,
            redirect_uri: this.options.redirectUri,
            scope: this.options.scope ?? 'openid profile email',
            state,
            code_challenge: challenge,
            code_challenge_method: 'S256',
        });
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
     * Exchanges the authorization code. Returns the path to redirect to.
     */
    public async handleCallback(
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
        sessionStorage.removeItem(key);
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

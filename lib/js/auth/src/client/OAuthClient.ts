import axios, {
    AxiosError,
    AxiosHeaders,
    AxiosInstance,
    InternalAxiosRequestConfig,
} from 'axios';
import {jwtDecode} from 'jwt-decode';
import {CookieStorage, IStorage} from '@alchemy/storage';
import {createHttpClient, HttpClient} from '@alchemy/api';
import type {
    AuthEvent,
    AuthEventHandler,
    AuthTokens,
    LoginEvent,
    LogoutEvent,
    LogoutOptions,
    OAuthClientOptions,
    RefreshTokenEvent,
    SessionExpiredEvent,
    TokenResponse,
    TokenResponseWithTokens,
    UserInfoResponse,
    ValidationError,
} from '../types';
import {GrantTypeRefreshMethod, OAuthEvent} from '../types';

type OrderedListener = {
    p: number;
    h: AuthEventHandler<any>;
};

export class OAuthClient<UIR extends UserInfoResponse> {
    public tokenPromise: Promise<any> | undefined;
    public readonly clientId: string;
    public readonly clientSecret: string | undefined;
    public readonly baseUrl: string;
    private listeners: Record<string, OrderedListener[]> = {};
    private readonly storage: IStorage;
    private initialized: boolean = false;
    private initPromise: Promise<AuthTokens | undefined> | undefined;
    private tokens: AuthTokens | undefined;
    private sessionTimeout: ReturnType<typeof setTimeout> | undefined;
    private autoRefreshTimeout: ReturnType<typeof setTimeout> | undefined;
    private readonly refreshTokenStorageKey: string;
    public readonly httpClient: HttpClient;
    private readonly scope?: string;
    public sessionHasExpired: boolean = false;
    public autoRefreshToken: boolean = true;
    public tokenValidityOffset: number = 5;
    public refreshTokenValidityOffset: number = 5;

    constructor({
        clientId,
        clientSecret,
        baseUrl,
        storage,
        refreshTokenStorageKey = 'token',
        httpClient,
        scope,
        cookiesOptions,
        autoRefreshToken,
    }: OAuthClientOptions) {
        this.clientId = clientId;
        this.clientSecret = clientSecret;
        this.baseUrl = baseUrl;
        this.storage =
            storage ??
            new CookieStorage({
                cookiesOptions,
            });
        this.refreshTokenStorageKey = refreshTokenStorageKey;
        this.httpClient = httpClient ?? createHttpClient(this.baseUrl);
        this.scope = scope;
        this.autoRefreshToken = autoRefreshToken ?? true;
    }

    public isValidSession(tokens: AuthTokens | undefined): boolean {
        if (tokens) {
            if (tokens.refreshToken) {
                return (
                    tokens.refreshExpiresAt! >
                    Math.ceil(new Date().getTime() / 1000) +
                        this.refreshTokenValidityOffset
                );
            }

            return (
                tokens.expiresAt >
                Math.ceil(new Date().getTime() / 1000) +
                    this.tokenValidityOffset
            );
        }

        return false;
    }

    public getAccessToken(): string | undefined {
        return this.tokens?.accessToken;
    }

    public getTokenType(): string | undefined {
        return this.tokens?.tokenType;
    }

    public getRefreshToken(): string | null {
        return this.storage.getItem(this.refreshTokenStorageKey);
    }

    public async isAuthenticated(): Promise<boolean> {
        return this.isValidSession(await this.initSession());
    }

    public hasSession(): boolean {
        return !!this.getRefreshToken();
    }

    public isAccessTokenValid(): boolean {
        if (this.tokens) {
            return (
                this.tokens.expiresAt >
                Math.ceil(new Date().getTime() / 1000) +
                    this.tokenValidityOffset
            );
        }

        return false;
    }

    public getDecodedToken(): UIR | undefined {
        const accessToken = this.getAccessToken();
        if (!accessToken) {
            return;
        }

        return jwtDecode<UIR>(accessToken);
    }

    public getDecodedIdToken(): UIR | undefined {
        const idToken = this.tokens?.idToken;
        if (!idToken) {
            return;
        }

        return jwtDecode<UIR>(idToken);
    }

    public async logout(
        options: LogoutOptions = {}
    ): Promise<LogoutEvent | undefined> {
        this.clearSessionTimeout();
        this.storage.removeItem(this.refreshTokenStorageKey);
        this.tokens = undefined;

        if (!options.noEvent) {
            const event = {
                ...options,
            } as LogoutEvent;

            await this.triggerEvent<LogoutEvent>(OAuthEvent.logout, event);

            return event;
        }
    }

    public registerListener<E extends AuthEvent = AuthEvent>(
        event: OAuthEvent,
        callback: AuthEventHandler<E>,
        priority: number = 0
    ): void {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push({p: priority, h: callback});
    }

    public unregisterListener<E extends AuthEvent = AuthEvent>(
        event: string,
        callback: AuthEventHandler<E>
    ): void {
        if (!this.listeners[event]) {
            return;
        }

        this.listeners[event] = this.listeners[event]!.filter(
            ({h}) => h !== callback
        );
    }

    public async getTokenFromAuthCode(
        code: string,
        redirectUri: string
    ): Promise<TokenResponseWithTokens> {
        const res = await this.getToken({
            code,
            grant_type: 'authorization_code',
            redirect_uri: redirectUri,
        });

        const {tokens} = res;

        await this.triggerLogin(tokens);

        return res;
    }

    async getTokenFromRefreshToken(): Promise<TokenResponseWithTokens> {
        try {
            const res = await this.getToken({
                refresh_token: this.getRefreshToken()!,
                grant_type: 'refresh_token',
            });

            const {tokens} = res;

            this.handleSessionTimeout(tokens);

            await this.triggerEvent<RefreshTokenEvent>(
                OAuthEvent.refreshToken,
                {
                    tokens,
                }
            );

            return res;
        } catch (e: any) {
            console.debug('e', e);
            if (axios.isAxiosError<ValidationError>(e)) {
                if (
                    e.status === 401 ||
                    e.response?.data?.error === 'invalid_grant'
                ) {
                    this.sessionExpired();
                }
            }

            throw e;
        }
    }

    async getTokenFromUsernamePassword(
        username: string,
        password: string,
        extraData: Record<string, any> = {}
    ): Promise<TokenResponseWithTokens> {
        const res = await this.getToken({
            grant_type: 'password',
            username,
            password,
            ...extraData,
        });

        const {tokens} = res;

        await this.triggerLogin(tokens);

        return res;
    }

    public async triggerLogin(tokens: AuthTokens): Promise<void> {
        this.handleSessionTimeout(tokens);

        await this.triggerEvent<LoginEvent>(OAuthEvent.login, {
            tokens,
        });
    }

    async getTokenFromCustomGrantType(
        data: Record<string, any> = {}
    ): Promise<TokenResponseWithTokens> {
        const res = await this.getToken(data);
        const {tokens} = res;

        await this.triggerLogin(tokens);

        return res;
    }

    public async getTokenFromClientCredentials(): Promise<TokenResponseWithTokens> {
        const res = await this.getToken({
            grant_type: 'client_credentials',
            client_id: this.clientId,
            client_secret: this.clientSecret,
        });
        const {tokens} = res;

        await this.triggerEvent<RefreshTokenEvent>(OAuthEvent.refreshToken, {
            tokens,
        });

        return res;
    }

    public createAuthorizeUrl({
        redirectPath = '/auth',
        connectTo,
        state,
    }: {
        redirectPath?: string;
        connectTo?: string | undefined;
        state?: string | undefined;
    }): string {
        const redirectUri = normalizeRedirectUri(redirectPath)!;
        const queryString = `response_type=code&client_id=${encodeURIComponent(this.clientId)}&redirect_uri=${encodeURIComponent(redirectUri)}${connectTo ? `&kc_idp_hint=${encodeURIComponent(connectTo)}` : ''}${state ? `&state=${encodeURIComponent(state)}` : ''}`;

        return `${this.baseUrl}/auth?${queryString}`;
    }

    public getTokens(): AuthTokens | undefined {
        return this.tokens;
    }

    private async triggerEvent<E extends AuthEvent = AuthEvent>(
        type: OAuthEvent,
        event: Omit<E, 'type'>
    ): Promise<void> {
        const e = event as E;
        e.type = type;

        if (!this.listeners[type]) {
            return Promise.resolve();
        }

        const orderedListeners = this.listeners[type];
        orderedListeners.sort((a, b) => a.p - b.p);

        await Promise.all(
            orderedListeners
                .map(({h}) => !e.stopPropagation && h(e))
                .filter(f => !!f)
        );
    }

    private handleSessionTimeout(tokens: AuthTokens): void {
        this.clearSessionTimeout();

        if (
            tokens.refreshExpiresIn &&
            tokens.refreshExpiresIn < 604800 // prevent too long setTimeout
        ) {
            this.sessionTimeout = setTimeout(() => {
                this.sessionExpired();
            }, tokens.refreshExpiresIn * 1000);

            if (this.autoRefreshToken) {
                this.autoRefreshTimeout = setTimeout(
                    () => {
                        if (!document.hidden) {
                            this.getTokenFromRefreshToken();
                        }
                    },
                    tokens.refreshExpiresIn * 1000 - 10000
                );
            }
        }
    }

    private sessionExpired(): void {
        this.sessionHasExpired = true;
        this.triggerEvent<SessionExpiredEvent>(OAuthEvent.sessionExpired, {});
        this.logout({
            quiet: true,
        });
    }

    private clearSessionTimeout(): void {
        clearTimeout(this.sessionTimeout);
        clearTimeout(this.autoRefreshTimeout);
    }

    private createAuthTokensFromResponse(res: TokenResponse): AuthTokens {
        const now = Math.ceil(new Date().getTime() / 1000);

        return {
            tokenType: res.token_type,
            accessToken: res.access_token,
            idToken: res.id_token,
            expiresIn: res.expires_in,
            expiresAt: now + res.expires_in,
            refreshToken: res.refresh_token,
            refreshExpiresIn: res.refresh_expires_in,
            refreshExpiresAt: now + res.refresh_expires_in,
            deviceToken: res.device_token,
            deviceTokenExpiresIn: res.device_token_expires_in,
            deviceTokenExpiresAt: res.device_token_expires_in
                ? now + res.device_token_expires_in
                : undefined,
        };
    }

    private persistTokens(tokens: AuthTokens): void {
        this.tokens = {
            ...tokens,
        };

        this.storage.setItem(this.refreshTokenStorageKey, tokens.refreshToken, {
            expires: new Date(tokens.refreshExpiresAt * 1000),
        });

        if (this.storage.getItem(this.refreshTokenStorageKey) === null) {
            console.error('Failed to persist token. Storage may be full or not writable.');
        }
    }

    public async initSession(): Promise<AuthTokens | undefined> {
        if (this.initialized) {
            return this.tokens;
        }

        if (!this.hasSession()) {
            this.initialized = true;

            return;
        }

        if (this.initPromise) {
            return await this.initPromise;
        }

        this.initPromise = new Promise<AuthTokens | undefined>((resolve, reject) => {
            this.getTokenFromRefreshToken().then(() => {
                this.initialized = true;
                resolve(this.tokens);
            }).catch((reason) => {
                this.initialized = true;
                reject(reason);
            }).finally(() => {
                this.initPromise = undefined;
            });
        });

        return await this.initPromise;
    }

    private async getToken(
        data: Record<string, string | undefined>
    ): Promise<TokenResponseWithTokens> {
        const params = new URLSearchParams();
        const formData: Record<string, string | undefined> = {
            ...data,
            client_id: this.clientId,
            client_secret: this.clientSecret,
            scope: this.scope,
        };

        Object.keys(formData).map(k => {
            if (undefined !== formData[k]) {
                params.append(k, formData[k] as string);
            }
        });

        const res = (await this.httpClient.post(`token`, params))
            .data as TokenResponse;

        return {
            ...res,
            tokens: this.saveTokensFromResponse(res),
        };
    }

    public saveTokensFromResponse(res: TokenResponse): AuthTokens {
        const tokens = this.createAuthTokensFromResponse(res);
        this.persistTokens(tokens);
        this.sessionHasExpired = false;

        return tokens;
    }
}

type OnTokenError = (error: AxiosError) => void;

export function configureClientAuthentication<UIR extends UserInfoResponse>(
    client: AxiosInstance,
    oauthClient: OAuthClient<UIR>,
    refreshMethod: GrantTypeRefreshMethod = GrantTypeRefreshMethod.refreshToken,
    onTokenError?: OnTokenError
): void {
    client.interceptors.request.use(
        createAxiosInterceptor(oauthClient, refreshMethod, onTokenError)
    );
}

export function configureClientCredentials401Retry<UIR extends UserInfoResponse>(
    client: AxiosInstance,
    oauthClient: OAuthClient<UIR>
): void {
    client.interceptors.response.use(
        r => r,
        async (error: AxiosError) => {
            if (
                error.config &&
                !error.config.anonymous &&
                !error.config.retryAfterNewToken &&
                error.response &&
                401 === error.response.status
            ) {
                await oauthClient.logout();

                try {
                    await oauthClient.getTokenFromClientCredentials();
                } catch (_e) {
                    throw error;
                }

                return await client.request({
                    ...error.config,
                    retryAfterNewToken: true,
                } as typeof error.config);
            }

            throw error;
        }
    );
}

function createAxiosInterceptor<UIR extends UserInfoResponse>(
    oauthClient: OAuthClient<UIR>,
    refreshMethod: GrantTypeRefreshMethod,
    onTokenError?: OnTokenError
) {
    return async (config: InternalAxiosRequestConfig) => {
        if (config.anonymous) {
            return config;
        }

        if (
            refreshMethod === GrantTypeRefreshMethod.refreshToken &&
            !await oauthClient.isAuthenticated()
        ) {
            return config;
        }

        if (!oauthClient.isAccessTokenValid()) {
            const p = oauthClient.tokenPromise;
            if (p) {
                await p;
            } else {
                const p = oauthClient[refreshMethod]();
                oauthClient.tokenPromise = p;

                try {
                    await p;
                } catch (e: any) {
                    if (e.isAxiosError) {
                        const err = e as AxiosError<any>;
                        if (err.response && 400 === err.response.status) {
                            oauthClient.logout();

                            onTokenError && onTokenError(err);

                            throw e;
                        }
                    }
                } finally {
                    oauthClient.tokenPromise = undefined;
                }
            }
        }

        config.headers ??= new AxiosHeaders({});
        config.headers['Authorization'] ??=
            `${oauthClient.getTokenType()!} ${oauthClient.getAccessToken()!}`;

        return config;
    };
}

export function normalizeRedirectUri(uri: string): string {
    if (uri.indexOf('/') === 0) {
        const url = [window.location.protocol, '//', window.location.host].join(
            ''
        );

        return `${url}${uri}`;
    }

    return uri;
}

export function inIframe(): boolean {
    try {
        return window.self !== window.top;
    } catch (e) {
        return true;
    }
}

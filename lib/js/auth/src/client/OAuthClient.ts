import axios, {AxiosError, AxiosHeaders, AxiosInstance, InternalAxiosRequestConfig} from "axios";
import {jwtDecode} from "jwt-decode";
import {CookieStorage, IStorage} from "@alchemy/storage";
import {createHttpClient, HttpClient} from "@alchemy/api";
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
    ValidationError
} from "../types";
import {GrantTypeRefreshMethod, OAuthEvent,} from "../types";

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
    private tokensCache: AuthTokens | undefined;
    private sessionTimeout: ReturnType<typeof setTimeout> | undefined;
    private autoRefreshTimeout: ReturnType<typeof setTimeout> | undefined;
    private readonly tokenStorageKey: string = 'token';
    private readonly httpClient: HttpClient;
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
        tokenStorageKey,
        httpClient,
        scope,
        cookiesOptions,
        autoRefreshToken,
    }: OAuthClientOptions) {
        this.clientId = clientId;
        this.clientSecret = clientSecret;
        this.baseUrl = baseUrl;
        this.storage = storage ?? new CookieStorage({
            cookiesOptions,
        });
        this.tokenStorageKey = tokenStorageKey ?? 'token';
        this.httpClient = httpClient ?? createHttpClient(this.baseUrl);
        this.scope = scope;
        this.autoRefreshToken = autoRefreshToken ?? true;
    }

    public isValidSession(tokens: AuthTokens | undefined): boolean {
        if (tokens) {
            if (tokens.refreshToken) {
                return tokens.refreshExpiresAt! > (Math.ceil(new Date().getTime() / 1000) + this.refreshTokenValidityOffset);
            }

            return tokens.expiresAt > (Math.ceil(new Date().getTime() / 1000) + this.tokenValidityOffset);
        }

        return false;
    }

    public isTokenPersisted(): boolean {
        return Boolean(this.storage.getItem(this.tokenStorageKey));
    }

    public getAccessToken(): string | undefined {
        return this.fetchTokens()?.accessToken;
    }

    public getTokenType(): string | undefined {
        return this.fetchTokens()?.tokenType;
    }

    public getRefreshToken(): string | undefined {
        return this.fetchTokens()?.refreshToken;
    }

    public isAuthenticated(): boolean {
        return this.isValidSession(this.fetchTokens());
    }

    public isAccessTokenValid(): boolean {
        const tokens = this.fetchTokens();
        if (tokens) {
            return tokens.expiresAt > (Math.ceil(new Date().getTime() / 1000) + this.tokenValidityOffset);
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

    public async logout(options: LogoutOptions = {}): Promise<LogoutEvent | undefined> {
        this.clearSessionTimeout();
        this.storage.removeItem(this.tokenStorageKey);
        this.tokensCache = undefined;

        if (!options.noEvent) {
            const event = {
                ...options,
            } as LogoutEvent;

            await this.triggerEvent<LogoutEvent>(OAuthEvent.logout, event);

            return event;
        }
    }

    public registerListener<E extends AuthEvent = AuthEvent>(event: OAuthEvent, callback: AuthEventHandler<E>, priority: number = 0): void {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push({p: priority, h: callback});
    }

    public unregisterListener<E extends AuthEvent = AuthEvent>(event: string, callback: AuthEventHandler<E>): void {
        if (!this.listeners[event]) {
            return;
        }

        this.listeners[event] = this.listeners[event]!.filter(({h}) => h !== callback);
    }

    public async getTokenFromAuthCode(code: string, redirectUri: string): Promise<TokenResponseWithTokens> {
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

            await this.triggerEvent<RefreshTokenEvent>(OAuthEvent.refreshToken, {
                tokens,
            });

            return res;
        } catch (e: any) {
            console.debug('e', e);
            if (axios.isAxiosError<ValidationError>(e)) {
                if (e.status === 401 || e.response?.data?.error === 'invalid_grant') {
                    this.sessionExpired();
                }
            }

            throw e;
        }
    }

    async getTokenFromUsernamePassword(username: string, password: string, extraData: Record<string, any> = {}): Promise<TokenResponseWithTokens> {
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

    async getTokenFromCustomGrantType(data: Record<string, any> = {}): Promise<TokenResponseWithTokens> {
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

    public async wrapPromiseWithValidToken<T = any>(callback: (tokens: AuthTokens) => Promise<T>): Promise<T> {
        if (!this.isAccessTokenValid()) {
            await this.getTokenFromRefreshToken();
        }

        return await callback(this.fetchTokens()!);
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
        return this.fetchTokens();
    }

    private async triggerEvent<E extends AuthEvent = AuthEvent>(type: OAuthEvent, event: Omit<E, "type">): Promise<void> {
        const e = event as E;
        e.type = type;

        if (!this.listeners[type]) {
            return Promise.resolve();
        }

        const orderedListeners = this.listeners[type];
        orderedListeners.sort((a, b) => a.p - b.p);

        await Promise.all(orderedListeners.map(({h}) => !e.stopPropagation && h(e)).filter(f => !!f));
    }

    private handleSessionTimeout(tokens: AuthTokens): void {
        this.clearSessionTimeout();

        if (tokens.refreshExpiresIn
            && tokens.refreshExpiresIn < 604800 // prevent too long setTimeout
        ) {
            this.sessionTimeout = setTimeout(() => {
                this.sessionExpired();
            }, tokens.refreshExpiresIn * 1000);

            if (this.autoRefreshToken) {
                this.autoRefreshTimeout = setTimeout(() => {
                    if (!document.hidden) {
                        this.getTokenFromRefreshToken();
                    }
                }, tokens.refreshExpiresIn * 1000 - 10000);
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
            expiresIn: res.expires_in,
            expiresAt: now + res.expires_in,
            refreshToken: res.refresh_token,
            refreshExpiresIn: res.refresh_expires_in,
            refreshExpiresAt: res.refresh_expires_in ? now + res.refresh_expires_in : undefined,
            deviceToken: res.device_token,
            deviceTokenExpiresIn: res.device_token_expires_in,
            deviceTokenExpiresAt: res.device_token_expires_in ? now + res.device_token_expires_in : undefined,
        };
    }

    private persistTokens(tokens: AuthTokens): void {
        this.tokensCache = tokens;

        this.storage.setItem(this.tokenStorageKey, JSON.stringify(tokens));
    }

    private fetchTokens(): AuthTokens | undefined {
        if (this.tokensCache) {
            return this.tokensCache;
        }

        const t = this.storage.getItem(this.tokenStorageKey);
        if (t) {
            const tokens = JSON.parse(t) as AuthTokens;

            this.handleSessionTimeout(tokens)

            return this.tokensCache = tokens;
        }
    }

    private async getToken(data: Record<string, string | undefined>): Promise<TokenResponseWithTokens> {
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

        const res = (await this.httpClient.post(`token`, params)).data as TokenResponse;

        return {
            ...res,
            tokens: this.saveTokensFromResponse(res)
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

export function configureClientAuthentication(
    client: AxiosInstance,
    oauthClient: OAuthClient<any>,
    refreshMethod: GrantTypeRefreshMethod = GrantTypeRefreshMethod.refreshToken,
    onTokenError?: OnTokenError
): void {
    client.interceptors.request.use(createAxiosInterceptor(oauthClient, refreshMethod, onTokenError));
}

export function configureClientCredentials401Retry(
    client: AxiosInstance,
    oauthClient: OAuthClient<any>,
): void {
    client.interceptors.response.use(r => r, async (error: AxiosError) => {
        if (error.config
            && !error.config.anonymous
            && !error.config.retryAfterNewToken
            && error.response
            && 401 === error.response.status
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
    });
}

function createAxiosInterceptor(
    oauthClient: OAuthClient<any>,
    refreshMethod: GrantTypeRefreshMethod,
    onTokenError?: OnTokenError
) {
    return async (config: InternalAxiosRequestConfig) => {
        if (config.anonymous) {
            return config;
        }

        if (refreshMethod === GrantTypeRefreshMethod.refreshToken && !oauthClient.isAuthenticated()) {
            return config;
        }

        if (!oauthClient.isAccessTokenValid()) {
            let p = oauthClient.tokenPromise;
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
        config.headers['Authorization'] ??= `${oauthClient.getTokenType()!} ${oauthClient.getAccessToken()!}`;

        return config;
    };
}

export function normalizeRedirectUri(uri: string): string {
    if (uri.indexOf('/') === 0) {
        const url = [
            window.location.protocol,
            '//',
            window.location.host,
        ].join('');

        return `${url}${uri}`;
    }

    return uri;
}

export function inIframe (): boolean {
    try {
        return window.self !== window.top;
    } catch (e) {
        return true;
    }
}

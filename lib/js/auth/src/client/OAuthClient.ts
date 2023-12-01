import axios, {AxiosError, AxiosHeaders, AxiosInstance, InternalAxiosRequestConfig} from "axios";
import {jwtDecode} from "jwt-decode";
import CookieStorage from "@alchemy/storage/src/CookieStorage";
import {IStorage} from "@alchemy/storage";

export type TokenResponse = {
    access_token: string;
    refresh_token: string;
    token_type: string;
    expires_in: number;
    refresh_expires_in: number;
    expires_at?: number;
    refresh_expires_at?: number;
};

interface ValidationError {
    error: string;
    error_description: string;
}

export type UserInfoResponse = {
    preferred_username: string;
    groups: string[];
    roles: string[];
    sub: string;
}

export type AuthEvent = {
    type: string;
};

export type LoginEvent = {
    response: TokenResponse;
} & AuthEvent;

export type RefreshTokenEvent = {
    response: TokenResponse;
} & AuthEvent;

export type LogoutEvent = AuthEvent;

export type AuthEventHandler<E extends AuthEvent = AuthEvent> = (event: E) => Promise<void>;

export const loginEventType = 'login';
export const refreshTokenEventType = 'refreshToken';
export const logoutEventType = 'logout';
export const sessionExpiredEventType = 'sessionExpired';


type Options = {
    storage?: IStorage;
    clientId: string;
    clientSecret?: string;
    baseUrl: string;
    tokenStorageKey?: string;
};

export type {Options as OAuthClientOptions};

export default class OAuthClient {
    public tokenPromise: Promise<any> | undefined;
    public readonly clientId: string;
    public readonly clientSecret: string | undefined;
    public readonly baseUrl: string;
    private listeners: Record<string, AuthEventHandler[]> = {};
    private readonly storage: IStorage;
    private tokensCache: TokenResponse | undefined;
    private sessionTimeout: ReturnType<typeof setTimeout> | undefined;
    private readonly tokenStorageKey: string = 'token';

    constructor({
        clientId,
        clientSecret,
        baseUrl,
        storage = new CookieStorage(),
        tokenStorageKey,
    }: Options) {
        this.clientId = clientId;
        this.clientSecret = clientSecret;
        this.baseUrl = baseUrl;

        if (!storage) {
            throw new Error(`Unable to store session`);
        }
        this.storage = storage;
        this.tokenStorageKey = tokenStorageKey ?? 'token';
    }

    public getAccessToken(): string | undefined {
        return this.fetchTokens()?.access_token;
    }

    public getTokenType(): string | undefined {
        return this.fetchTokens()?.token_type;
    }

    public getRefreshToken(): string | undefined {
        return this.fetchTokens()?.refresh_token;
    }

    public getUsername(): string | undefined {
        return this.getDecodedToken()?.preferred_username;
    }

    public isAuthenticated(): boolean {
        const tokens = this.fetchTokens();

        if (tokens) {
            return tokens.refresh_expires_at! > (Math.ceil(new Date().getTime() / 1000) + 1);
        }

        return false;
    }

    public isAccessTokenValid(): boolean {
        const tokens = this.fetchTokens();
        if (tokens) {
            return tokens.expires_at! > (Math.ceil(new Date().getTime() / 1000) + 1);
        }

        return false;
    }

    public getDecodedToken(): UserInfoResponse | undefined {
        const accessToken = this.getAccessToken();
        if (!accessToken) {
            return;
        }

        return jwtDecode<UserInfoResponse>(accessToken);
    }

    public logout(): void {
        this.clearSessionTimeout();

        this.triggerEvent(logoutEventType);
        this.storage.removeItem(this.tokenStorageKey);
        this.tokensCache = undefined;
    }

    public registerListener(event: string, callback: AuthEventHandler): void {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }

    public unregisterListener(event: string, callback: AuthEventHandler): void {
        if (!this.listeners[event]) {
            return;
        }

        const index = this.listeners[event].findIndex(c => c === callback);
        if (index >= 0) {
            delete this.listeners[event][index];
        }
    }

    public async getTokenFromAuthCode(code: string, redirectUri: string): Promise<TokenResponse> {
        const res = await this.getToken({
            code,
            grant_type: 'authorization_code',
            redirect_uri: redirectUri,
        });

        this.persistTokens(res);

        this.handleSessionTimeout(res);

        await this.triggerEvent<LoginEvent>(loginEventType, {
            response: res,
        });

        return res;
    }

    async getTokenFromRefreshToken(): Promise<TokenResponse> {
        try {
            const res = await this.getToken({
                refresh_token: this.getRefreshToken()!,
                grant_type: 'refresh_token',
            });

            this.handleSessionTimeout(res);

            await this.triggerEvent<RefreshTokenEvent>(refreshTokenEventType, {
                response: res,
            });

            return res;
        } catch (e: any) {
            console.debug('e', e);
            if (axios.isAxiosError<ValidationError>(e)) {
                if (e.response?.data?.error === 'invalid_grant') {
                    this.sessionExpired();
                }
            }

            throw e;
        }
    }

    public async getTokenFromClientCredentials(): Promise<TokenResponse> {
        const res = await this.getToken({
            grant_type: 'client_credentials',
            client_id: this.clientId,
            client_secret: this.clientSecret,
        });

        await this.triggerEvent<RefreshTokenEvent>(refreshTokenEventType, {
            response: res,
        });

        return res;
    }

    public async wrapPromiseWithValidToken<T = any>(callback: (tokens: TokenResponse) => Promise<T>): Promise<T> {
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

    public getTokenResponse(): TokenResponse | undefined {
        return this.fetchTokens();
    }

    private async triggerEvent<E extends AuthEvent = AuthEvent>(type: string, event: Partial<E> = {}): Promise<void> {
        event.type = type;

        if (!this.listeners[type]) {
            return Promise.resolve();
        }

        await Promise.all(this.listeners[type].map(func => func(event as E)).filter(f => !!f));
    }

    private handleSessionTimeout(res: TokenResponse): void {
        this.clearSessionTimeout();

        this.sessionTimeout = setTimeout(() => {
            this.sessionExpired();
        }, res.refresh_expires_in * 1000);
    }

    private sessionExpired(): void {
        this.triggerEvent<LogoutEvent>(sessionExpiredEventType);
        this.logout();
    }

    private clearSessionTimeout(): void {
        if (this.sessionTimeout) {
            clearTimeout(this.sessionTimeout);
            this.sessionTimeout = undefined;
        }
    }

    private persistTokens(token: TokenResponse): void {
        const now = Math.ceil(new Date().getTime() / 1000);
        token.expires_at = now + token.expires_in;
        token.refresh_expires_at = now + token.refresh_expires_in;
        this.tokensCache = token;

        this.storage.setItem(this.tokenStorageKey, JSON.stringify(token));
    }

    private fetchTokens(): TokenResponse | undefined {
        if (this.tokensCache) {
            return this.tokensCache;
        }

        const t = this.storage.getItem(this.tokenStorageKey);
        if (t) {
            return this.tokensCache = JSON.parse(t) as TokenResponse;
        }
    }

    private async getToken(data: Record<string, string | undefined>): Promise<TokenResponse> {
        const params = new URLSearchParams();
        const formData: Record<string, string | undefined> = {
            ...data,
            client_id: this.clientId,
            client_secret: this.clientSecret,
        };

        Object.keys(formData).map(k => {
            if (undefined !== formData[k]) {
                params.append(k, formData[k] as string);
            }
        });

        const res = (await axios.post(`${this.baseUrl}/token`, params)).data as TokenResponse;

        this.persistTokens(res);

        return res;
    }
}

type OnTokenError = (error: AxiosError) => void;

export function configureClientAuthentication(
    client: AxiosInstance,
    oauthClient: OAuthClient,
    onTokenError?: OnTokenError
): void {
    client.interceptors.request.use(createAxiosInterceptor(oauthClient, "getTokenFromRefreshToken", onTokenError));
}

export function configureClientCredentialsGrantType(
    client: AxiosInstance,
    oauthClient: OAuthClient,
    onTokenError?: OnTokenError
): void {
    client.interceptors.request.use(createAxiosInterceptor(oauthClient, "getTokenFromClientCredentials", onTokenError));
}

function createAxiosInterceptor(
    oauthClient: OAuthClient,
    method: "getTokenFromRefreshToken" | "getTokenFromClientCredentials",
    onTokenError?: OnTokenError
) {
    return async (config: InternalAxiosRequestConfig) => {
        if (method === "getTokenFromRefreshToken" && (config.anonymous || !oauthClient.isAuthenticated())) {
            return config;
        }

        if (!oauthClient.isAccessTokenValid()) {
            let p = oauthClient.tokenPromise;
            if (p) {
                await p;
            } else {
                const p = oauthClient[method]();
                oauthClient.tokenPromise = p;

                try {
                    await p;
                } catch (e: any) {
                    if (e.isAxiosError) {
                        const err = e as AxiosError<any>;
                        if (err.response && [400].includes(err.response.status)) {
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

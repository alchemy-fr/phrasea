import axios, {Axios, AxiosInstance, AxiosRequestConfig} from "axios";
import CookieStorage from "./cookieStorage";
import jwtDecode from "jwt-decode";

const tokenStorageKey = 'token';

type TokenResponse = {
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

type UserInfoResponse = {
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

export interface IStorage {
    getItem(key: string): string | null;

    removeItem(key: string): void;

    setItem(key: string, value: string): void;
}

type Options = {
    storage?: IStorage;
    clientId: string;
    baseUrl: string;
    realm: string;
}

export default class OAuthClient {
    public tokenPromise: Promise<any> | undefined;
    private listeners: Record<string, AuthEventHandler[]> = {};
    private readonly clientId: string;
    private readonly baseUrl: string;
    private realm: string;
    private storage: IStorage;
    private tokensCache: TokenResponse | undefined;
    private sessionTimeout: ReturnType<typeof setTimeout> | undefined;

    constructor({
        clientId,
        baseUrl,
        realm,
        storage = new CookieStorage()
    }: Options) {
        this.clientId = clientId;
        this.baseUrl = baseUrl;
        this.realm = realm;

        if (!storage) {
            throw new Error(`Unable to store session`);
        }
        this.storage = storage;
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
        console.debug('isAuthenticated tokens', tokens);

        if (tokens) {
            return tokens.refresh_expires_at! > (Math.ceil(new Date().getTime() / 1000) + 1);
        }

        return false;
    }

    public isAccessTokenValid(): boolean {
        const tokens = this.fetchTokens();
        console.debug('isAccessTokenValid tokens', tokens);
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

    logout(redirectPath: string | false = '/'): void {
        this.clearSessionTimeout();

        this.doLogout();

        if (false !== redirectPath) {
            document.location.href = this.createLogoutUrl({redirectPath});
        }
    }

    registerListener(event: string, callback: AuthEventHandler): void {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }

    unregisterListener(event: string, callback: AuthEventHandler): void {
        if (!this.listeners[event]) {
            return;
        }

        const index = this.listeners[event].findIndex(c => c === callback);
        if (index >= 0) {
            delete this.listeners[event][index];
        }
    }

    public async getAccessTokenFromAuthCode(code: string, redirectUri: string): Promise<TokenResponse> {
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

    async refreshToken(): Promise<TokenResponse> {
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
            console.log('e', e);
            if (axios.isAxiosError<ValidationError>(e)) {
                if (e.response?.data?.error === 'invalid_grant') {
                    this.logout();
                }
            }

            throw e;
        }
    }

    public async wrapPromiseWithValidToken<T = any>(callback: (tokens: TokenResponse) => Promise<T>): Promise<T> {
        if (!this.isAccessTokenValid()) {
            await this.refreshToken();
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
        const redirectUri = this.normalizeRedirectUri(redirectPath)!;
        const queryString = `response_type=code&client_id=${encodeURIComponent(this.clientId)}&redirect_uri=${encodeURIComponent(redirectUri)}${connectTo ? `&kc_idp_hint=${encodeURIComponent(connectTo)}` : ''}${state ? `&state=${encodeURIComponent(state)}` : ''}`;

        return `${this.getOpenIdConnectBaseUrl()}/auth?${queryString}`;
    }

    private normalizeRedirectUri(uri: string): string {
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

    private getRealmUrl(): string {
        return `${this.baseUrl}/realms/${this.realm}`;
    }

    private getOpenIdConnectBaseUrl(): string {
        return `${this.getRealmUrl()}/protocol/openid-connect`;
    }

    public getAccountUrl(redirect: string = '/'): string {
        redirect ??= document.location.toString();
        const redirectUri = this.normalizeRedirectUri(redirect);

        return `${this.getRealmUrl()}/account/?referrer=${this.clientId}&referrer_uri=${encodeURIComponent(redirectUri)}#/personal-info`
    }

    public createLogoutUrl({
        redirectPath = '/',
    }: {
        redirectPath?: string;
    }): string {
        const redirectUri = this.normalizeRedirectUri(redirectPath);
        const queryString = `client_id=${encodeURIComponent(this.clientId)}&post_logout_redirect_uri=${encodeURIComponent(redirectUri)}`;

        return `${this.getOpenIdConnectBaseUrl()}/logout?${queryString}`;
    }

    public getTokenResponse(): TokenResponse | undefined {
        return this.fetchTokens();
    }

    private doLogout(): void {
        this.triggerEvent(logoutEventType);
        this.storage.removeItem(tokenStorageKey);
        this.tokensCache = undefined;
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
        this.doLogout();
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

        this.storage.setItem(tokenStorageKey, JSON.stringify(token));
    }

    private fetchTokens(): TokenResponse | undefined {
        if (this.tokensCache) {
            return this.tokensCache;
        }

        const t = this.storage.getItem(tokenStorageKey);
        if (t) {
            return this.tokensCache = JSON.parse(t) as TokenResponse;
        }
    }

    private async getToken(data: Record<string, string>): Promise<TokenResponse> {
        const params = new URLSearchParams();
        const formData: Record<string, string> = {
            ...data,
            client_id: this.clientId,
        };

        Object.keys(formData).map(k => {
            params.append(k, formData[k]);
        });

        const res = (await axios.post(`${this.getOpenIdConnectBaseUrl()}/token`, params)).data as TokenResponse;

        this.persistTokens(res);

        return res;
    }
}

export type RequestConfigWithAuth = {
    anonymous?: boolean;
} & AxiosRequestConfig;

export function configureClientAuthentication(client: AxiosInstance, oauthClient: OAuthClient): void {
    client.interceptors.request.use(async (config: RequestConfigWithAuth) => {
        if (config.anonymous || !oauthClient.isAuthenticated()) {
            return config;
        }

        if (!oauthClient.isAccessTokenValid()) {
            let p = oauthClient.tokenPromise;
            if (p) {
                await p;
            } else {
                const p = oauthClient.refreshToken();
                oauthClient.tokenPromise = p;

                try {
                    await p;
                } finally {
                    oauthClient.tokenPromise = undefined;
                }
            }
        }

        config.headers ??= {};
        config.headers['Authorization'] = `${oauthClient.getTokenType()!} ${oauthClient.getAccessToken()!}`;

        return config;
    });
}

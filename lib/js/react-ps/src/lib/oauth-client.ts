import axios, {AxiosInstance, AxiosRequestConfig} from "axios";
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

type UserInfoResponse = {
    preferred_username: string;
    groups: string[];
    roles: string[];
    sub: string;
}

type AuthEvent = {
    type: string;
};

type LoginEvent = {
    accessToken: string;
} & AuthEvent;

type AuthenticationEvent = {
    user: UserInfoResponse;
} & AuthEvent;

type LogoutEvent = AuthEvent;

type AuthEventHandler = (event: AuthEvent) => Promise<void>;

export const authenticationEventType = 'authentication';
export const loginEventType = 'login';
export const logoutEventType = 'logout';

export interface IStorage {
    getItem(key: string): string | null;

    removeItem(key: string): void;

    setItem(key: string, value: string): void;
}

type Options = {
    storage?: IStorage;
    clientId: string;
    baseUrl: string;
}

export default class OAuthClient {
    private listeners: Record<string, AuthEventHandler[]> = {};
    private clientId: string;
    private baseUrl: string;
    private storage: IStorage;
    private tokensCache: TokenResponse | undefined;

    constructor({
        clientId,
        baseUrl,
        storage = new CookieStorage()
    }: Options) {
        this.clientId = clientId;
        this.baseUrl = baseUrl;

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

    logout(redirectPath: string = '/'): void {
        this.storage.removeItem(tokenStorageKey);
        this.tokensCache = undefined;
        this.triggerEvent(logoutEventType);

        document.location.href = this.createLogoutUrl({redirectPath});
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

    private async triggerEvent<E extends AuthEvent = AuthEvent>(type: string, event: Partial<E> = {}): Promise<void> {
        event.type = type;

        if (!this.listeners[type]) {
            return Promise.resolve();
        }

        await Promise.all(this.listeners[type].map(func => func(event as E)).filter(f => !!f));
    }

    public async getAccessTokenFromAuthCode(code: string, redirectUri: string): Promise<TokenResponse> {
        const res = await this.getToken({
            code,
            grant_type: 'authorization_code',
            redirect_uri: redirectUri,
        });

        this.persistTokens(res);

        await this.triggerEvent(loginEventType);

        return res;
    }

    async refreshToken(): Promise<TokenResponse> {
        const res = await this.getToken({
            refresh_token: this.getRefreshToken()!,
            grant_type: 'refresh_token',
        });

        await this.triggerEvent(loginEventType);

        return res;
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
        const baseUrl = [
            window.location.protocol,
            '//',
            window.location.host,
        ].join('');

        const redirectUri = `${redirectPath.indexOf('/') === 0 ? baseUrl : ''}${redirectPath}`;
        const queryString = `response_type=code&client_id=${encodeURIComponent(this.clientId)}&redirect_uri=${encodeURIComponent(redirectUri)}${connectTo ? `&connect=${encodeURIComponent(connectTo)}` : ''}${state ? `&state=${encodeURIComponent(state)}` : ''}`;

        return `${this.baseUrl}/auth?${queryString}`;
    }

    public createLogoutUrl({
        redirectPath = '/',
    }: {
        redirectPath?: string;
    }): string {
        const baseUrl = [
            window.location.protocol,
            '//',
            window.location.host,
        ].join('');

        const redirectUri = `${redirectPath.indexOf('/') === 0 ? baseUrl : ''}${redirectPath}`;
        const queryString = `client_id=${encodeURIComponent(this.clientId)}&post_logout_redirect_uri=${encodeURIComponent(redirectUri)}`;

        return `${this.baseUrl}/logout?${queryString}`;
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

        const res = (await axios.post(`${this.baseUrl}/token`, params)).data as TokenResponse;

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
            await oauthClient.refreshToken();
        }

        config.headers ??= {};
        config.headers['Authorization'] = `${oauthClient.getTokenType()!} ${oauthClient.getAccessToken()!}`;

        return config;
    });
}

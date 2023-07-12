import axios, {AxiosError} from "axios";
import CookieStorage from "./cookieStorage";

const tokenStorageKey = 'token';
const usernameStorageKey = 'username';

type TokenResponse = {
    access_token: string;
    refresh_token: string;
    expires_in: number;
};

type UserInfoResponse = {
    username: string;
    email: string;
    groups: Record<string, string>;
    roles: string[];
    user_id: string;
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
    private authenticated = false;
    private clientId: string;
    private baseUrl: string;
    private storage: IStorage;

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

    hasAccessToken(): boolean {
        return null !== this.getAccessToken();
    }

    public getAccessToken(): string | null {
        return this.getTokenInfo()?.access_token ?? null;
    }

    public getRefreshToken(): string | null {
        return this.getTokenInfo()?.refresh_token ?? null;
    }

    private setTokenInfo(token: TokenResponse): void {
        return this.storage.setItem(tokenStorageKey, JSON.stringify(token));
    }

    private getTokenInfo(): TokenResponse | undefined {
        const t = this.storage.getItem(tokenStorageKey);
        if (t) {
            return JSON.parse(t) as TokenResponse;
        }
    }

    /**
     * @deprecated
     */
    public setUsername(username: string): void {
        return this.storage.setItem(usernameStorageKey, username);
    }

    /**
     * @deprecated
     */
    public getUsername(): string | null {
        return this.storage.getItem(usernameStorageKey);
    }

    isAuthenticated(): boolean {
        return this.authenticated;
    }

    logout(): void {
        this.authenticated = false;
        this.storage.removeItem(tokenStorageKey);
        this.storage.removeItem(usernameStorageKey);
        this.triggerEvent(logoutEventType);
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

    async triggerEvent<E extends AuthEvent = AuthEvent>(type: string, event: Partial<E> = {}): Promise<void> {
        event.type = type;

        if (!this.listeners[type]) {
            return Promise.resolve();
        }

        await Promise.all(this.listeners[type].map(func => func(event as E)).filter(f => !!f));
    }

    async authenticate(authUrl?: string): Promise<UserInfoResponse> {
        if (!this.hasAccessToken()) {
            throw new Error(`Missing access token`);
        }

        try {
            const data = (await axios.get(authUrl ?? `${this.baseUrl}/userinfo`, {
                headers: {
                    authorization: `Bearer ${this.getAccessToken()}`,
                } as any
            })).data as UserInfoResponse;

            this.authenticated = true;
            await this.triggerEvent<AuthenticationEvent>(authenticationEventType, {user: data});
            this.setUsername(data.username);

            return data;
        } catch (e: any) {
            if (axios.isAxiosError(e)) {
                const err = e as AxiosError;

                if (err.response?.status === 401) {
                    this.logout();
                }
            }

            throw e;
        }
    }

    async getAccessTokenFromAuthCode(code: string, redirectUri: string): Promise<TokenResponse> {
        const res = await this.getToken({
            code,
            grant_type: 'authorization_code',
            redirect_uri: redirectUri,
        });

        await this.triggerEvent(loginEventType);

        return res;
    }

    async login(username: string, password: string): Promise<TokenResponse> {
        const res = await this.doLogin(username, password);
        await this.triggerEvent(loginEventType);

        return res;
    }

    private async doLogin(username: string, password: string): Promise<TokenResponse> {
        return await this.getToken({
            username,
            password,
            grant_type: 'password',
        });
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

        this.setTokenInfo(res);

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
        const baseUrl = [
            window.location.protocol,
            '//',
            window.location.host,
        ].join('');

        const redirectUri = `${redirectPath.indexOf('/') === 0 ? baseUrl : ''}${redirectPath}`;
        const queryString = `response_type=code&client_id=${encodeURIComponent(this.clientId)}&redirect_uri=${encodeURIComponent(redirectUri)}${connectTo ? `&connect=${encodeURIComponent(connectTo)}` : ''}${state ? `&state=${encodeURIComponent(state)}` : ''}`;

        return `${this.baseUrl}/auth?${queryString}`;
    }
}

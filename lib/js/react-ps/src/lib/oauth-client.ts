import axios from "axios";

const accessTokenStorageKey = 'accessToken';
const usernameStorageKey = 'username';

type TokenResponse = {
    access_token: string;
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

type Options = {
    storage?: Storage;
    clientId: string;
    clientSecret: string;
    baseUrl: string;
}

export default class OAuthClient {
    private listeners: Record<string, AuthEventHandler[]> = {};
    private authenticated = false;
    private clientId: string;
    private clientSecret: string;
    private baseUrl: string;
    private storage: Storage;

    constructor({
                    clientId,
                    clientSecret,
                    baseUrl,
                    storage = sessionStorage
                }: Options) {
        this.clientId = clientId;
        this.clientSecret = clientSecret;
        this.baseUrl = baseUrl;

        if (!storage) {
            throw new Error(`Unable to store session`);
        }
        this.storage = storage;
    }

    hasAccessToken(): boolean {
        return null !== this.getAccessToken();
    }

    getAccessToken(): string | null {
        return this.storage.getItem(accessTokenStorageKey);
    }

    setAccessToken(accessToken: string): void {
        return this.storage.setItem(accessTokenStorageKey, accessToken);
    }

    setUsername(username: string): void {
        return this.storage.setItem(usernameStorageKey, username);
    }

    getUsername(): string | null {
        return this.storage.getItem(usernameStorageKey);
    }

    isAuthenticated(): boolean {
        return this.authenticated;
    }

    logout(): void {
        this.authenticated = false;
        this.setAccessToken('');
        this.setUsername('');
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

    async authenticate(): Promise<UserInfoResponse> {
        if (!this.hasAccessToken()) {
            throw new Error(`Missing access token`);
        }

        const data = (await axios.get(`${this.baseUrl}/userinfo`, {
            headers: {
                authorization: `Bearer ${this.getAccessToken()}`,

            } as any
        })).data as UserInfoResponse;

        this.authenticated = true;
        await this.triggerEvent<AuthenticationEvent>(authenticationEventType, {user: data});
        this.setUsername(data.username);

        return data;
    }

    async getAccessTokenFromAuthCode(code: string, redirectUri: string): Promise<{
        username: string;
    }> {
        const {clientId, clientSecret, baseUrl} = this;

        const data = (await axios.post(`${baseUrl}/oauth/v2/token`, {
            code,
            grant_type: 'authorization_code',
            client_id: clientId,
            client_secret: clientSecret,
            redirect_uri: redirectUri,
        })).data as {
            username: string;
        } & TokenResponse;

        this.setAccessToken(data.access_token);
        await this.triggerEvent(loginEventType);

        return data;
    }

    async login(username: string, password: string): Promise<TokenResponse> {
        const res = await this.doLogin(username, password);
        await this.triggerEvent(loginEventType);

        return res;
    }

    private async doLogin(username: string, password: string): Promise<TokenResponse> {
        const {clientId, clientSecret, baseUrl} = this;

        const data = (await axios.post(`${baseUrl}/oauth/v2/token`, {
            username,
            password,
            grant_type: 'password',
            client_id: clientId,
            client_secret: clientSecret,
        })).data as TokenResponse;

        this.setAccessToken(data.access_token);

        return data
    }
}

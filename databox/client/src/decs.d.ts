declare module "@alchemy-fr/phraseanet-react-components";

declare class OAuthClient {
    hasAccessToken(): boolean;

    getAccessToken(): string | null;

    setAccessToken(accessToken: string | null): void;

    setUsername(username: string | null);

    getUsername(): string | null

    isAuthenticated(): boolean;

    logout(): void;

    registerListener(event: string, callback: () => any): void;

    unregisterListener(event: string, callback: () => any): void;

    async authenticate(uri: string): Promise<void>;

    getAccessTokenFromAuthCode(code: string, redirectUri: string): Promise<any>;

    async login(username: string, password: string): Promise<any>;
}

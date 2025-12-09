import {normalizeRedirectUri, OAuthClient} from './OAuthClient';
import {
    KeycloakClientOptions,
    KeycloakUserInfoResponse,
    LogoutEvent,
    LogoutOptions,
    OAuthEvent,
} from '../types';
import Keycloak from 'keycloak-js';

export class KeycloakClient {
    private readonly baseUrl: string;
    private readonly realm: string;
    public readonly client: OAuthClient<KeycloakUserInfoResponse>;
    private readonly keycloak: Keycloak;
    private initialized: boolean = false;

    constructor({realm, baseUrl, clientId, ...rest}: KeycloakClientOptions) {
        this.realm = realm;
        this.baseUrl = baseUrl;
        this.client = new OAuthClient({
            baseUrl: this.getOpenIdConnectBaseUrl(),
            clientId,
            ...rest,
        });

        this.client.registerListener(
            OAuthEvent.logout,
            this.onLogout.bind(this),
            255
        );

        this.keycloak = new Keycloak({
            url: baseUrl,
            realm,
            clientId,
        });
    }

    public async initKeycloakSession(): Promise<void> {
        if (this.initialized) {
            return;
        }
        this.initialized = true;

        if (await this.client.isAuthenticated()) {
            return;
        }

        const authenticated = await this.keycloak.init({
            onLoad: 'check-sso',
            silentCheckSsoRedirectUri: `${location.origin}/silent-check-sso.html`,
        });

        if (authenticated) {
            const {
                refreshTokenParsed,
                tokenParsed,
                refreshToken,
                token,
                idToken,
            } = this.keycloak;

            this.client.saveTokensFromResponse({
                id_token: idToken!,
                access_token: token!,
                expires_in: tokenParsed!.exp! - tokenParsed!.iat!,
                refresh_token: refreshToken!,
                refresh_expires_in:
                    refreshTokenParsed!.exp! - refreshTokenParsed!.iat!,
                token_type: 'Bearer',
            });

            const tokens = this.client.getTokens();
            if (tokens) {
                await this.client.triggerLogin(tokens);
            }
        }
    }

    private async onLogout(event: LogoutEvent): Promise<void> {
        if (!event.quiet) {
            await this.logout(
                {
                    ...event,
                },
                event
            );
        }
    }

    private getRealmUrl(): string {
        return `${this.baseUrl}/realms/${this.realm}`;
    }

    private getOpenIdConnectBaseUrl(): string {
        return `${this.getRealmUrl()}/protocol/openid-connect`;
    }

    public getAccountUrl(redirect: string = '/'): string {
        redirect ??= document.location.toString();
        const redirectUri = normalizeRedirectUri(redirect);

        return `${this.getRealmUrl()}/account/?referrer=${this.client.clientId}&referrer_uri=${encodeURIComponent(redirectUri)}#/personal-info`;
    }

    public createLogoutUrl({
        redirectPath = '/',
    }: {
        redirectPath?: string;
    }): string {
        const redirectUri = normalizeRedirectUri(redirectPath);
        const queryString = `client_id=${encodeURIComponent(this.client.clientId)}&post_logout_redirect_uri=${encodeURIComponent(redirectUri)}`;

        return `${this.getOpenIdConnectBaseUrl()}/logout?${queryString}`;
    }

    public async logout(
        {redirectPath = '/', ...options}: LogoutOptions = {},
        event?: LogoutEvent
    ): Promise<void> {
        await this.client.logout({
            ...options,
            noEvent: true,
        });

        if (redirectPath) {
            if (event) {
                event.stopPropagation = true;
                event.preventDefault = true;
            }

            const url = new URL(redirectPath, document.location.href);
            url.searchParams.set('logout', '1');
            document.location.href = this.createLogoutUrl({
                redirectPath: url.toString(),
            });
        }
    }
}

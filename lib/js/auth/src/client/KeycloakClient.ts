import {normalizeRedirectUri, OAuthClient} from './OAuthClient';
import {
    KeycloakClientOptions,
    KeycloakUserInfoResponse,
    LogoutEvent,
    LogoutOptions,
    OAuthEvent,
} from '../types';

export class KeycloakClient {
    private readonly baseUrl: string;
    private readonly realm: string;
    public readonly client: OAuthClient<KeycloakUserInfoResponse>;

    constructor({realm, baseUrl, ...rest}: KeycloakClientOptions) {
        this.realm = realm;
        this.baseUrl = baseUrl;
        this.client = new OAuthClient({
            baseUrl: this.getOpenIdConnectBaseUrl(),
            ...rest,
        });

        this.client.registerListener(
            OAuthEvent.logout,
            this.onLogout.bind(this),
            255
        );
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

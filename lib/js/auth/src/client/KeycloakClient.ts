import OAuthClient, {
    LogoutEvent,
    logoutEventType,
    LogoutOptions,
    normalizeRedirectUri,
    OAuthClientOptions
} from "./OAuthClient";

type Options = {
    realm: string;
} & OAuthClientOptions;

export default class KeycloakClient {
    private readonly baseUrl: string;
    private readonly realm: string;
    public readonly client: OAuthClient;

    constructor({
        realm,
        baseUrl,
        ...rest
    }: Options) {
        this.realm = realm;
        this.baseUrl = baseUrl;
        this.client = new OAuthClient({
            baseUrl: this.getOpenIdConnectBaseUrl(),
            ...rest,
        });

        this.client.registerListener(logoutEventType, this.onLogout.bind(this));
    }

    private async onLogout(options: LogoutEvent): Promise<void>
    {
        if (!options.quiet) {
            this.logout(options);
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

        return `${this.getRealmUrl()}/account/?referrer=${this.client.clientId}&referrer_uri=${encodeURIComponent(redirectUri)}#/personal-info`
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

    public logout({
        redirectPath = '/',
        ...options
    }: LogoutOptions): void {
        this.client.logout({
            ...options,
            noEvent: true,
        });

        if (redirectPath) {
            document.location.href = this.createLogoutUrl({redirectPath});
        }
    }
}

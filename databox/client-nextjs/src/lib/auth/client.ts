import {getConfig} from '@/lib/config/ConfigProvider';
import {OidcClient} from './oidc';

let client: OidcClient | undefined;

export const AUTH_CALLBACK_PATH = '/auth/callback';

export function getAuthClient(): OidcClient {
    if (client) {
        return client;
    }
    const config = getConfig();
    const origin =
        typeof window !== 'undefined'
            ? window.location.origin
            : config.clientUrl;

    client = new OidcClient({
        issuerUrl: `${config.keycloak.url}/realms/${config.keycloak.realm}`,
        clientId: config.keycloak.clientId,
        redirectUri: `${origin}${AUTH_CALLBACK_PATH}`,
        postLogoutRedirectUri: `${origin}/`,
        idpHint: config.keycloak.autoConnectIdP,
    });

    return client;
}

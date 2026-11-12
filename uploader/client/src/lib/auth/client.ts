import {getConfig} from '@/lib/config/ConfigProvider';
import {OidcClient} from '@alchemy/next-core/auth/oidc';

let client: OidcClient | undefined;

export const AUTH_CALLBACK_PATH = '/auth/callback';

/** Mirrors `LOGGED_OUT_PARAM` of the AuthProvider (kept free of React here) */
const LOGGED_OUT_QUERY = 'logged_out=1';

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
        silentRedirectUri: `${origin}/silent-check-sso.html`,
        postLogoutRedirectUri: `${origin}/?${LOGGED_OUT_QUERY}`,
        idpHint: config.keycloak.autoConnectIdP,
        storageKey: 'upl.auth',
    });

    return client;
}

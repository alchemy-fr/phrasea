import type {AppConfig} from '@/lib/config/types';

/**
 * Public discovery document served at `/phrasea-manifest.json`: third-party
 * integrations (plugins, CLI…) find the Keycloak endpoints and the Databox API
 * from the client URL alone.
 */
export type PhraseaManifest = {
    authUrl: string;
    tokenUrl: string;
    userinfoUrl: string;
    logoutUrl: string;
    openidConfigurationUrl: string;
    databoxApiUrl: string;
};

export function buildPhraseaManifest(config: AppConfig): PhraseaManifest {
    const {url, realm, autoConnectIdP} = config.keycloak;
    const realmUrl = `${url}/realms/${realm}`;
    const oidcUrl = `${realmUrl}/protocol/openid-connect`;

    return {
        authUrl: `${oidcUrl}/auth${autoConnectIdP ? `?kc_idp_hint=${encodeURIComponent(autoConnectIdP)}` : ''}`,
        tokenUrl: `${oidcUrl}/token`,
        userinfoUrl: `${oidcUrl}/userinfo`,
        logoutUrl: `${oidcUrl}/logout`,
        openidConfigurationUrl: `${realmUrl}/.well-known/openid-configuration`,
        databoxApiUrl: config.apiUrl,
    };
}

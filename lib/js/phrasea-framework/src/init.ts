import {initSentry} from '@alchemy/core';
import {configureClientAuthentication, KeycloakClient} from '@alchemy/auth';
import {createHttpClient} from '@alchemy/api';
import {createInstance} from '@jonkoops/matomo-tracker-react';

type Props = {
    config?: WindowConfig;
    appName: string;
};

export function initApp({
    config: overriddenConfig,
    appName,
}: Props) {
    const config = overriddenConfig ?? window?.config ?? ({} as WindowConfig);
    config.appName = appName;

    initSentry(config);

    const keycloakClient = new KeycloakClient({
        clientId: config.clientId,
        baseUrl: config.keycloakUrl,
        realm: config.realmName,
        cookiesOptions: {
            sameSite: 'none',
        },
    });
    const oauthClient = keycloakClient.client;
    const apiClient = createHttpClient(config.baseUrl);
    configureClientAuthentication(apiClient, oauthClient);

    const analytics = config.analytics;
    const matomoConfig = analytics?.matomo;

    const matomo = matomoConfig
        ? createInstance({
              urlBase: matomoConfig.baseUrl,
              siteId: parseInt(matomoConfig.siteId),
              linkTracking: false,
              configurations: {
                  setSecureCookie: true,
              },
          })
        : undefined;

    return {
        apiClient,
        oauthClient,
        keycloakClient,
        config,
        matomo,
    };
}

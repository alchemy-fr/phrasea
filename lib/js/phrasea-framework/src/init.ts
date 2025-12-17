import {initSentry, WindowConfig} from '@alchemy/core';
import {configureClientAuthentication, KeycloakClient} from '@alchemy/auth';
import {createHttpClient} from '@alchemy/api';
import {createInstance} from '@jonkoops/matomo-tracker-react';

type Props<T extends WindowConfig> = {
    config?: T;
    appName: string;
};

export function initApp<T extends WindowConfig>({
    config: overriddenConfig,
    appName,
}: Props<T>) {
    const config = overriddenConfig ?? window?.config ?? {};
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

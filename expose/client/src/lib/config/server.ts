import 'server-only';
import {sentryClientConfigFromEnv} from '@alchemy/next-core/monitoring/config';
import type {AppConfig} from './types';

function bool(value: string | undefined, fallback = false): boolean {
    if (value === undefined || value === '') {
        return fallback;
    }

    return ['1', 'true', 'on', 'y', 'yes'].includes(value.toLowerCase());
}

/**
 * Builds the runtime configuration from the process environment.
 * Must only be called from server components / route handlers.
 */
export function getServerConfig(): AppConfig {
    const env = process.env;

    return {
        appId: env.APP_ID || 'expose',
        clientUrl: env.EXPOSE_CLIENT_URL ?? '',
        apiUrl: env.EXPOSE_API_URL ?? '',
        keycloak: {
            url: env.KEYCLOAK_URL ?? '',
            realm: env.KEYCLOAK_REALM_NAME ?? 'phrasea',
            clientId: env.CLIENT_ID ?? 'expose-app',
            autoConnectIdP: env.AUTO_CONNECT_IDP || undefined,
        },
        dashboardUrl: env.DASHBOARD_CLIENT_URL || undefined,
        displayServicesMenu: bool(env.DISPLAY_SERVICES_MENU),
        disableIndexPage: bool(env.DISABLE_INDEX_PAGE),
        analytics:
            env.MATOMO_URL && env.MATOMO_SITE_ID
                ? {
                      matomo: {
                          baseUrl: env.MATOMO_URL,
                          siteId: env.MATOMO_SITE_ID,
                          mediaPluginEnabled: bool(
                              env.MATOMO_MEDIA_PLUGIN_ENABLED
                          ),
                          disableCookies: bool(env.MATOMO_DISABLE_COOKIES),
                      },
                  }
                : {},
        sentry: sentryClientConfigFromEnv(env),
        logo: env.APP_LOGO_SRC ? {src: env.APP_LOGO_SRC} : undefined,
    };
}

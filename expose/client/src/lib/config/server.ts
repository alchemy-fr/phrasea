import 'server-only';
import {sentryClientConfigFromEnv} from '@alchemy/core/monitoring/config';
import {analyticsConfigFromEnv} from '@alchemy/core/analytics/config';
import {envBool} from '@alchemy/core/config/env';
import type {AppConfig} from './types';

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
        displayServicesMenu: envBool(env.DISPLAY_SERVICES_MENU),
        disableIndexPage: envBool(env.DISABLE_INDEX_PAGE),
        analytics: analyticsConfigFromEnv(env, {mediaPlugin: true}),
        sentry: sentryClientConfigFromEnv(env),
        logo: env.APP_LOGO_SRC ? {src: env.APP_LOGO_SRC} : undefined,
    };
}

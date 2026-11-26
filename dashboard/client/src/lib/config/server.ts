import 'server-only';
import {sentryClientConfigFromEnv} from '@alchemy/core/monitoring/config';
import {analyticsConfigFromEnv} from '@alchemy/core/analytics/config';
import {envBool} from '@alchemy/core/config/env';
import type {AppConfig, AppId, AppService, LinkId, ToolId} from './types';
import type {StackConfig} from '@alchemy/core/config/stackConfig';

type Env = Record<string, string | undefined>;

function url(value: string | undefined): string | undefined {
    return value?.trim().replace(/\/+$/, '') || undefined;
}

/** The environment variables of an application of the stack */
const appEnv: Record<AppId, {api: string; client: string}> = {
    databox: {api: 'DATABOX_API_URL', client: 'DATABOX_CLIENT_URL'},
    expose: {api: 'EXPOSE_API_URL', client: 'EXPOSE_CLIENT_URL'},
    uploader: {api: 'UPLOADER_API_URL', client: 'UPLOADER_CLIENT_URL'},
};

const toolEnv: Record<ToolId, string> = {
    pgadmin: 'PGADMIN_URL',
    phpmyadmin: 'PHPMYADMIN_URL',
    mailpit: 'MAILPIT_URL',
    matomo: 'MATOMO_URL',
    rabbitmq: 'RABBITMQ_CONSOLE_URL',
    traefik: 'TRAEFIK_CONSOLE_URL',
    sockudo: 'SOCKUDO_USAGE_URL',
};

const linkEnv: Record<LinkId, string> = {
    parade: 'PARADE_CLIENT_URL',
    consent: 'CONSENT_CLIENT_URL',
    lend: 'LEND_CLIENT_URL',
    order: 'ORDER_CLIENT_URL',
    documentation: 'DASHBOARD_DOCUMENTATION_URL',
};

/** The URL of each entry of `keys` whose variable is set */
function urls<Id extends string>(
    keys: Record<Id, string>,
    env: Env
): Partial<Record<Id, string>> {
    const values: Partial<Record<Id, string>> = {};
    for (const [id, key] of Object.entries(keys) as [Id, string][]) {
        const value = url(env[key]);
        if (value) {
            values[id] = value;
        }
    }

    return values;
}

/** The applications deployed on the stack: those whose API URL is set */
export function getApps(env: Env = process.env): AppConfig['apps'] {
    const apps: AppConfig['apps'] = {};
    for (const [id, keys] of Object.entries(appEnv) as [
        AppId,
        (typeof appEnv)[AppId],
    ][]) {
        const apiUrl = url(env[keys.api]);
        if (apiUrl) {
            apps[id] = {
                apiUrl,
                clientUrl: url(env[keys.client]),
            } satisfies AppService;
        }
    }

    return apps;
}

function logoFrom(stackConfig: StackConfig): AppConfig['logo'] {
    const logo = stackConfig.logo as
        {src?: unknown; style?: unknown} | undefined;
    if (typeof logo?.src === 'string' && logo.src) {
        return {
            src: logo.src,
            style: typeof logo.style === 'string' ? logo.style : undefined,
        };
    }

    return undefined;
}

/**
 * Builds the runtime configuration from the process environment and the
 * stack configuration. Must only be called from server components / route
 * handlers.
 */
export function getServerConfig(
    stackConfig: StackConfig = {},
    env: Env = process.env
): AppConfig {
    return {
        appId: env.APP_ID || 'dashboard',
        clientUrl: url(env.DASHBOARD_CLIENT_URL) ?? '',
        keycloak: {
            url: url(env.KEYCLOAK_URL) ?? '',
            realm: env.KEYCLOAK_REALM_NAME || 'phrasea',
            clientId: env.CLIENT_ID || 'dashboard-app',
            autoConnectIdP: env.AUTO_CONNECT_IDP || undefined,
        },
        stack: {
            name: env.STACK_NAME || undefined,
            version: env.STACK_VERSION || undefined,
        },
        apps: getApps(env),
        tools: urls(toolEnv, env),
        links: urls(linkEnv, env),
        devMode: envBool(env.DEV_MODE),
        analytics: analyticsConfigFromEnv(env),
        sentry: sentryClientConfigFromEnv(env),
        logo: logoFrom(stackConfig),
    };
}

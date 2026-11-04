import 'server-only';
import type {AppConfig, AppId, AppService, ToolId} from './types';
import type {StackConfig} from './stackConfig';

type Env = Record<string, string | undefined>;

function bool(value: string | undefined, fallback = false): boolean {
    if (value === undefined || value === '') {
        return fallback;
    }

    return ['1', 'true', 'on', 'y', 'yes'].includes(value.toLowerCase());
}

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
    const tools: AppConfig['tools'] = {};
    for (const [id, key] of Object.entries(toolEnv) as [ToolId, string][]) {
        const value = url(env[key]);
        if (value) {
            tools[id] = value;
        }
    }

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
        tools,
        devMode: bool(env.DEV_MODE),
        analytics:
            env.MATOMO_URL && env.MATOMO_SITE_ID
                ? {
                      matomo: {
                          baseUrl: env.MATOMO_URL,
                          siteId: env.MATOMO_SITE_ID,
                          disableCookies: bool(env.MATOMO_DISABLE_COOKIES),
                      },
                  }
                : {},
        logo: logoFrom(stackConfig),
    };
}

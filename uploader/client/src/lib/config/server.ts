import 'server-only';
import type {AppConfig} from './types';
import type {StackConfig} from '@alchemy/next-core/config/stackConfig';

function bool(value: string | undefined, fallback = false): boolean {
    if (value === undefined || value === '') {
        return fallback;
    }

    return ['1', 'true', 'on', 'y', 'yes'].includes(value.toLowerCase());
}

/** A positive integer from an env variable or a stack configuration entry */
export function positiveInt(value: unknown): number | undefined {
    if (typeof value === 'number') {
        return Number.isFinite(value) && value > 0
            ? Math.floor(value)
            : undefined;
    }
    if (typeof value !== 'string' || !value.trim()) {
        return undefined;
    }
    const n = parseInt(value, 10);

    return Number.isNaN(n) || n <= 0 ? undefined : n;
}

/**
 * Parses the `ALLOWED_FILE_TYPES` env format:
 * `image/*(.jpg,.png),application/pdf,video/*(.mp4)`
 */
export function parseAllowedTypes(
    value: string | undefined
): Record<string, string[]> {
    const struct: Record<string, string[]> = {};
    if (!value?.trim()) {
        return struct;
    }

    for (const m of value.matchAll(/([\w*]+\/[\w*+.-]+)(\([.\w,]*\))?/g)) {
        struct[m[1]] = m[2]
            ? m[2]
                  .slice(1, -1)
                  .split(',')
                  .map(e => e.trim())
                  .filter(Boolean)
            : [];
    }

    return struct;
}

type UploaderStackConfig = {
    max_upload_file_size?: unknown;
    max_upload_commit_size?: unknown;
    max_upload_file_count?: unknown;
    client?: {logo?: {src?: unknown; margin?: unknown}};
};

function logoFrom(
    stackConfig: StackConfig,
    uploader: UploaderStackConfig
): AppConfig['logo'] {
    const own = uploader.client?.logo;
    if (typeof own?.src === 'string' && own.src) {
        return {
            src: own.src,
            style:
                typeof own.margin === 'string' && own.margin
                    ? `margin: ${own.margin}`
                    : undefined,
        };
    }
    const global = stackConfig.logo as
        {src?: unknown; style?: unknown} | undefined;
    if (typeof global?.src === 'string' && global.src) {
        return {
            src: global.src,
            style: typeof global.style === 'string' ? global.style : undefined,
        };
    }

    return undefined;
}

/**
 * Builds the runtime configuration from the process environment and the
 * stack configuration (`uploader.*` entries of the configurator).
 * Must only be called from server components / route handlers.
 */
export function getServerConfig(
    stackConfig: StackConfig = {},
    env: Record<string, string | undefined> = process.env
): AppConfig {
    const uploader = (stackConfig.uploader ?? {}) as UploaderStackConfig;

    return {
        appId: env.APP_ID || 'uploader',
        appName: 'uploader',
        clientUrl: env.UPLOADER_CLIENT_URL ?? '',
        apiUrl: env.UPLOADER_API_URL ?? '',
        keycloak: {
            url: env.KEYCLOAK_URL ?? '',
            realm: env.KEYCLOAK_REALM_NAME ?? 'phrasea',
            clientId: env.CLIENT_ID ?? 'uploader-app',
            autoConnectIdP: env.AUTO_CONNECT_IDP || undefined,
        },
        dashboardUrl: env.DASHBOARD_CLIENT_URL || undefined,
        displayServicesMenu: bool(env.DISPLAY_SERVICES_MENU),
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
        sentry: env.SENTRY_DSN
            ? {
                  dsn: env.SENTRY_DSN,
                  environment: env.SENTRY_ENVIRONMENT,
                  release: env.SENTRY_RELEASE,
              }
            : undefined,
        upload: {
            maxFileSize:
                positiveInt(uploader.max_upload_file_size) ??
                positiveInt(env.S3_MAX_OBJECT_SIZE),
            maxCommitSize: positiveInt(uploader.max_upload_commit_size),
            maxFileCount: positiveInt(uploader.max_upload_file_count),
            allowedTypes: parseAllowedTypes(env.ALLOWED_FILE_TYPES),
        },
        logo: logoFrom(stackConfig, uploader),
    };
}

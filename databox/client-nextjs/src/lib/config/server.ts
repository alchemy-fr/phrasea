import 'server-only';
import type {AppConfig} from './types';

function bool(value: string | undefined, fallback = false): boolean {
    if (value === undefined || value === '') {
        return fallback;
    }

    return ['1', 'true', 'on', 'y', 'yes'].includes(value.toLowerCase());
}

function int(value: string | undefined): number | undefined {
    if (!value) {
        return undefined;
    }
    const n = parseInt(value, 10);

    return Number.isNaN(n) ? undefined : n;
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

/**
 * Builds the runtime configuration from the process environment.
 * Must only be called from server components / route handlers.
 */
export function getServerConfig(): AppConfig {
    const env = process.env;
    const keycloakUrl = env.KEYCLOAK_URL ?? '';
    const notifications = bool(env.NOTIFICATIONS_ENABLED);

    return {
        appId: env.APP_ID || 'databox',
        appName: 'databox',
        clientUrl: env.DATABOX_NEXT_CLIENT_URL ?? env.DATABOX_CLIENT_URL ?? '',
        apiUrl: env.DATABOX_API_URL ?? '',
        keycloak: {
            url: keycloakUrl,
            realm: env.KEYCLOAK_REALM_NAME ?? 'phrasea',
            clientId: env.CLIENT_ID ?? 'databox-app',
            autoConnectIdP: env.AUTO_CONNECT_IDP || undefined,
        },
        dashboardUrl: env.DASHBOARD_CLIENT_URL || undefined,
        displayServicesMenu: bool(env.DISPLAY_SERVICES_MENU),
        devMode: bool(env.DEV_MODE),
        requestSignatureTtl: int(env.S3_REQUEST_SIGNATURE_TTL),
        realtime:
            env.SOKETI_HOST && env.SOKETI_KEY
                ? {host: env.SOKETI_HOST, key: env.SOKETI_KEY}
                : undefined,
        notifications,
        analytics:
            env.MATOMO_URL && env.MATOMO_SITE_ID
                ? {
                      matomo: {
                          baseUrl: env.MATOMO_URL,
                          siteId: env.MATOMO_SITE_ID,
                          mediaPluginEnabled: bool(
                              env.MATOMO_MEDIA_PLUGIN_ENABLED
                          ),
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
            minChunkSize: int(env.S3_MULTIPART_MIN_CHUNK_SIZE),
            maxChunkSize: int(env.S3_MULTIPART_MAX_CHUNK_SIZE),
            maxPartNumber: int(env.S3_MULTIPART_MAX_PART_NUMBER),
            maxFileSize: int(env.S3_MAX_OBJECT_SIZE),
            allowedTypes: parseAllowedTypes(env.ALLOWED_FILE_TYPES),
        },
        logo: env.APP_LOGO_SRC ? {src: env.APP_LOGO_SRC} : undefined,
    };
}

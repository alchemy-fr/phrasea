(function (config, env) {
    config = config || {};

    const analytics = {};

    if (env.MATOMO_URL) {
        analytics.matomo = {
            baseUrl: env.MATOMO_URL,
            siteId: env.MATOMO_SITE_ID,
        };
    }

    const normalizeTypes = value => {
        if (!value) {
            return {};
        }
        const v = value.trim();

        if (!v) {
            return {};
        }

        const types = [...v.matchAll(/([\w*]+\/[\w*+.-]+)(\([.\w,]*\))?/g)];
        const struct = {};
        for (const t of types) {
            struct[t[1]] = t[2]
                ? t[2]
                      .substring(1, t[2].length - 1)
                      .split(',')
                      .map(e => e.trim())
                      .filter(e => !!e)
                : [];
        }

        return struct;
    };

    function castBoolean(value) {
        if (typeof value === 'boolean') {
            return value;
        }

        if (typeof value === 'string') {
            return ['true', '1', 'on', 'y', 'yes'].includes(
                value.toLowerCase()
            );
        }

        return false;
    }

    return {
        locales: config.available_locales,
        autoConnectIdP: env.AUTO_CONNECT_IDP,
        baseUrl: env.DATABOX_API_URL,
        uploaderApiBaseUrl: env.UPLOADER_API_URL,
        uploaderTargetSlug: env.UPLOADER_TARGET_SLUG,
        keycloakUrl: env.KEYCLOAK_URL,
        realmName: env.KEYCLOAK_REALM_NAME,
        clientId: env.CLIENT_ID,
        devMode: castBoolean(env.DEV_MODE),
        requestSignatureTtl: env.S3_REQUEST_SIGNATURE_TTL,
        displayServicesMenu: castBoolean(env.DISPLAY_SERVICES_MENU),
        dashboardBaseUrl: env.DASHBOARD_CLIENT_URL,
        allowedTypes: normalizeTypes(env.ALLOWED_FILE_TYPES),
        analytics,
        appId: env.APP_ID || 'databox',
        sentryDsn: env.SENTRY_DSN,
        sentryEnvironment: env.SENTRY_ENVIRONMENT,
        sentryRelease: env.SENTRY_RELEASE,
    };
});

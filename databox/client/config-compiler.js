(function (config, env) {
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

    function castInteger(value) {
        if (typeof value === 'number') {
            return value;
        }

        if (value && typeof value === 'string') {
            const parsed = parseInt(value, 10);
            return Number.isNaN(parsed) ? undefined : parsed;
        }

        return undefined;
    }

    config = config || {};

    const analytics = {};

    if (env.MATOMO_URL && env.MATOMO_SITE_ID) {
        analytics.matomo = {
            baseUrl: env.MATOMO_URL,
            siteId: env.MATOMO_SITE_ID,
            mediaPluginEnabled: castBoolean(env.MATOMO_MEDIA_PLUGIN_ENABLED),
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

    const stackConfig = JSON.parse(
        require('node:fs').readFileSync('/etc/app/stack-config.json', 'utf8')
    );
    const customHTML = {};
    customHTML['__MUI_THEME__'] = '';
    if (stackConfig.theme) {
        customHTML['__MUI_THEME__'] = `<script>
window.config = window.config || {};
window.config.muiTheme = ${stackConfig.theme.replace(/^export\s+const\s+themeOptions\s*=\s*/, '')}
</script>`;
    }

    let notifications = undefined;
    if (env.NOTIFICATIONS_ENABLED) {
        notifications = {
            appIdentifier: env.NOVU_APPLICATION_IDENTIFIER,
            socketUrl: env.NOVU_WS_URL,
            apiUrl: env.NOVU_API_URL,
        };
    }

    const realmName = env.KEYCLOAK_REALM_NAME;
    const redirectUri = `${env.DATABOX_CLIENT_URL}/auth`;
    const autoConnectIdP = env.AUTO_CONNECT_IDP;
    const authUrl = `${env.KEYCLOAK_URL}/realms/${realmName}/protocol/openid-connect/auth?response_type=code&client_id=${encodeURIComponent(env.CLIENT_ID)}&redirect_uri=${encodeURIComponent(redirectUri)}${autoConnectIdP ? `&kc_idp_hint=${encodeURIComponent(autoConnectIdP)}` : ''}`;

    require('node:fs').writeFileSync(
        'phrasea-manifest.json',
        JSON.stringify({
            authUrl,
        }),
        'utf8'
    );

    return {
        customHTML,
        logo: stackConfig.logo,
        autoConnectIdP,
        baseUrl: env.DATABOX_API_URL,
        keycloakUrl: env.KEYCLOAK_URL,
        realmName,
        clientId: env.CLIENT_ID,
        devMode: castBoolean(env.DEV_MODE),
        requestSignatureTtl: env.S3_REQUEST_SIGNATURE_TTL,
        displayServicesMenu: castBoolean(env.DISPLAY_SERVICES_MENU),
        dashboardBaseUrl: env.DASHBOARD_CLIENT_URL,
        analytics,
        appId: env.APP_ID || 'databox',
        sentryDsn: env.SENTRY_DSN,
        sentryEnvironment: env.SENTRY_ENVIRONMENT,
        sentryRelease: env.SENTRY_RELEASE,
        pusherHost: env.SOKETI_HOST,
        pusherKey: env.SOKETI_KEY,
        notifications,
        upload: {
            minChunkSize: castInteger(env.S3_MULTIPART_MIN_CHUNK_SIZE),
            maxChunkSize: castInteger(env.S3_MULTIPART_MAX_CHUNK_SIZE),
            maxPartNumber: castInteger(env.S3_MULTIPART_MAX_PART_NUMBER),
            maxFileSize: castInteger(env.S3_MAX_OBJECT_SIZE),
            allowedTypes: normalizeTypes(env.ALLOWED_FILE_TYPES),
        },
    };
});

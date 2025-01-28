(function (config, env) {
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

    return {
        customHTML,
        maxFileSize: config.uploader.max_upload_file_size,
        maxCommitSize: config.uploader.max_upload_commit_size,
        maxFileCount: config.uploader.max_upload_file_count,
        autoConnectIdP: env.AUTO_CONNECT_IDP,
        client: config.uploader.client,
        baseUrl: env.UPLOADER_API_URL,
        keycloakUrl: env.KEYCLOAK_URL,
        realmName: env.KEYCLOAK_REALM_NAME,
        clientId: env.CLIENT_ID,
        devMode: castBoolean(env.DEV_MODE),
        displayServicesMenu: castBoolean(env.DISPLAY_SERVICES_MENU),
        dashboardBaseUrl: env.DASHBOARD_CLIENT_URL,
        allowedTypes: normalizeTypes(env.ALLOWED_FILE_TYPES),
        appId: env.APP_ID || 'uploader',
        sentryDsn: env.SENTRY_DSN,
        sentryEnvironment: env.SENTRY_ENVIRONMENT,
        sentryRelease: env.SENTRY_RELEASE,
    };
});

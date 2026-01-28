(function (config, env) {
    config = config || {};

    const analytics = {};

    if (env.MATOMO_URL && env.MATOMO_SITE_ID) {
        analytics.matomo = {
            baseUrl: env.MATOMO_URL,
            siteId: env.MATOMO_SITE_ID,
        };
    }

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
        autoConnectIdP: env.AUTO_CONNECT_IDP,
        baseUrl: env.EXPOSE_API_URL,
        keycloakUrl: env.KEYCLOAK_URL,
        realmName: env.KEYCLOAK_REALM_NAME,
        clientId: env.CLIENT_ID,
        displayServicesMenu: castBoolean(env.DISPLAY_SERVICES_MENU),
        requestSignatureTtl: env.S3_REQUEST_SIGNATURE_TTL
            ? parseInt(env.S3_REQUEST_SIGNATURE_TTL)
            : 86400,
        disableIndexPage: castBoolean(env.DISABLE_INDEX_PAGE),
        analytics,
        appId: env.APP_ID || 'expose',
        sentryDsn: env.SENTRY_DSN,
        sentryEnvironment: env.SENTRY_ENVIRONMENT,
        sentryRelease: env.SENTRY_RELEASE,
    };
});

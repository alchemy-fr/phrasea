(function (config, env) {
    config = config || {};

    const analytics = {};

    if (env.MATOMO_URL) {
        analytics.matomo = {
            baseUrl: env.MATOMO_URL,
            siteId: env.MATOMO_SITE_ID,
        };
    }

    const whiteList = [
        'DATABOX_API_URL',
        'DATABOX_CLIENT_URL',
        'DEV_MODE',
        'DISPLAY_SERVICES_MENU',
        'ELASTICHQ_URL',
        'EXPOSE_API_URL',
        'EXPOSE_CLIENT_URL',
        'KEYCLOAK_URL',
        'MAILHOG_URL',
        'MATOMO_URL',
        'PGADMIN_URL',
        'PHPMYADMIN_URL',
        'RABBITMQ_CONSOLE_URL',
        'REPORT_API_URL',
        'SAML2_URL',
        'SAML_URL',
        'STACK_NAME',
        'STACK_VERSION',
        'TRAEFIK_CONSOLE_URL',
        'UPLOADER_API_URL',
        'UPLOADER_CLIENT_URL',
        'ZIPPY_URL',
        'SOKETI_USAGE_URL',
        'NOVU_DASHBOARD_URL',
    ];

    const e = {};

    Object.entries(env).forEach(([key, value]) => {
        if (whiteList.includes(key)) {
            e[key] = value;
        }
    });

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
        logo: stackConfig.logo,
        locales: config.available_locales,
        autoConnectIdP: env.AUTO_CONNECT_IDP,
        baseUrl: env.DASHBOARD_CLIENT_URL,
        keycloakUrl: env.KEYCLOAK_URL,
        realmName: env.KEYCLOAK_REALM_NAME,
        clientId: env.CLIENT_ID,
        devMode: castBoolean(env.DEV_MODE),
        displayServicesMenu: castBoolean(env.DISPLAY_SERVICES_MENU),
        dashboardBaseUrl: env.DASHBOARD_CLIENT_URL,
        analytics,
        appId: env.APP_ID || 'dashboard',
        sentryDsn: env.SENTRY_DSN,
        sentryEnvironment: env.SENTRY_ENVIRONMENT,
        sentryRelease: env.SENTRY_RELEASE,
        env: e,
    };
});

(function (config, env) {
    config = config || {};

    const analytics = {};

    if (env.MATOMO_URL) {
        analytics.matomo = {
            baseUrl: env.MATOMO_URL,
            siteId: env.MATOMO_SITE_ID,
        };
    }

    return {
        locales: config.available_locales,
        autoConnectIdP: env.AUTO_CONNECT_IDP,
        baseUrl: env.EXPOSE_API_URL,
        keycloakUrl: env.KEYCLOAK_URL,
        realmName: env.KEYCLOAK_REALM_NAME,
        clientId: env.CLIENT_ID,
        requestSignatureTtl: env.S3_REQUEST_SIGNATURE_TTL ? parseInt(env.S3_REQUEST_SIGNATURE_TTL) : 86400,
        disableIndexPage: ['true', '1', 'on'].includes(env.DISABLE_INDEX_PAGE),
        analytics,
    };
});

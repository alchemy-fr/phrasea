(function (config, env) {
    config = config || {};

    const identityProviders = config.auth && config.auth.identity_providers ? config.auth.identity_providers.map(idp => {
        delete idp.options;
        delete idp.group_jq_normalizer;
        delete idp.group_map;

        return idp;
    }) : [];

    const analytics = {};

    if (env.MATOMO_BASE_URL) {
        analytics.matomo = {
            baseUrl: env.MATOMO_BASE_URL,
            siteId: env.MATOMO_SITE_ID,
        };
    }

    return {
        locales: config.available_locales,
        identityProviders,
        loginFormLayout: config.auth.loginFormLayout,
        autoConnectIdP: env.AUTO_CONNECT_IDP,
        baseUrl: env.EXPOSE_API_BASE_URL,
        authBaseUrl: env.AUTH_API_BASE_URL,
        matomoBaseUrl: env.MATOMO_BASE_URL,
        clientId: env.CLIENT_ID + '_' + env.CLIENT_RANDOM_ID,
        clientSecret: env.CLIENT_SECRET,
        requestSignatureTtl: env.S3_REQUEST_SIGNATURE_TTL,
        disableIndexPage: ['true', '1', 'on'].includes(env.DISABLE_INDEX_PAGE),
        analytics,
    };
});

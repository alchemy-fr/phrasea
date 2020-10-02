(function (config, env) {
    config = config || {};

    const identityProviders = config.auth && config.auth.identity_providers ? config.auth.identity_providers.map(idp => {
        delete idp.options;

        return idp;
    }) : [];

    return {
        matomoHost: env.MATOMO_HOST,
        locales: config.available_locales,
        identityProviders,
        baseUrl: env.EXPOSE_API_BASE_URL,
        authBaseUrl: env.AUTH_BASE_URL,
        clientId: env.CLIENT_ID+'_'+env.CLIENT_RANDOM_ID,
        clientSecret: env.CLIENT_SECRET,
        devMode: env.DEV_MODE === 'true',
        mapBoxToken: env.MAPBOX_TOKEN,
        requestSignatureTtl: env.EXPOSE_REQUEST_SIGNATURE_TTL,
        displayServicesMenu: env.DISPLAY_SERVICES_MENU === 'true',
        dashboardBaseUrl: env.DASHBOARD_BASE_URL
    };
});

(function (config, env) {
    const identityProviders = config.auth.identity_providers.map(idp => {
        delete idp.options;
        delete idp.group_jq_normalizer;
        delete idp.group_map;

        return idp;
    });

    return {
        locales: config.available_locales,
        maxFileSize: config.uploader.max_upload_file_size,
        maxCommitSize: config.uploader.max_upload_commit_size,
        maxFileCount: config.uploader.max_upload_file_count,
        loginFormLayout: config.auth.loginFormLayout,
        autoConnectIdP: env.AUTO_CONNECT_IDP,
        client: config.uploader.client,
        identityProviders,
        baseUrl: env.UPLOADER_API_BASE_URL,
        authBaseUrl: env.AUTH_API_BASE_URL,
        clientId: env.CLIENT_ID+'_'+env.CLIENT_RANDOM_ID,
        clientSecret: env.CLIENT_SECRET,
        devMode: env.DEV_MODE === 'true',
        displayServicesMenu: env.DISPLAY_SERVICES_MENU === 'true',
        dashboardBaseUrl: env.DASHBOARD_BASE_URL
    };
});

(function (config, env) {
    const identityProviders = config.auth.identity_providers.map(idp => {
        delete idp.options;

        return idp;
    });

    return {
        locales: config.available_locales,
        maxFileSize: config.uploader.max_upload_file_size,
        maxCommitSize: config.uploader.max_upload_commit_size,
        maxFileCount: config.uploader.max_upload_file_count,
        client: config.uploader.client,
        identityProviders,
        baseUrl: env.UPLOADER_API_BASE_URL,
        authBaseUrl: env.AUTH_BASE_URL,
        clientId: env.CLIENT_ID+'_'+env.CLIENT_RANDOM_ID,
        clientSecret: env.CLIENT_SECRET,
        devMode: env.DEV_MODE === 'true',
    };
});

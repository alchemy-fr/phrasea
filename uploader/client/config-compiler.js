(function (config, env) {
    const normalizeTypes = value => {
        if (!value) {
            return {};
        }
        const v = value.trim();

        if (!v) {
            return {};
        }

        const types = [...v.matchAll(/([\w*]+\/[\w*+.-]+)(\([\w,]*\))?/g)];
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

    return {
        locales: config.available_locales,
        maxFileSize: config.uploader.max_upload_file_size,
        maxCommitSize: config.uploader.max_upload_commit_size,
        maxFileCount: config.uploader.max_upload_file_count,
        autoConnectIdP: env.AUTO_CONNECT_IDP,
        client: config.uploader.client,
        baseUrl: env.UPLOADER_API_URL,
        keycloakUrl: env.KEYCLOAK_URL,
        realmName: env.KEYCLOAK_REALM_NAME,
        clientId: env.CLIENT_ID,
        devMode: env.DEV_MODE === 'true',
        displayServicesMenu: env.DISPLAY_SERVICES_MENU === 'true',
        dashboardBaseUrl: env.DASHBOARD_URL,
        allowedTypes: normalizeTypes(env.ALLOWED_FILE_TYPES),
    };
});

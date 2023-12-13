(function (config, env) {
    const whiteList = [
    'DATABOX_API_URL',
    'DATABOX_CLIENT_URL',
    'DEV_MODE',
    'DISPLAY_SERVICES_MENU',
    'DOCKER_TAG',
    'ELASTICHQ_URL',
    'EXPOSE_API_URL',
    'EXPOSE_CLIENT_URL',
    'KEYCLOAK_URL',
    'MAILHOG_URL',
    'MATOMO_URL',
    'NOTIFY_API_URL',
    'PGADMIN_URL',
    'PHPMYADMIN_URL',
    'RABBITMQ_CONSOLE_URL',
    'REPORT_API_URL',
    'SAML_URL',
    'SAML2_URL',
    'STACK_NAME',
    'TRAEFIK_CONSOLE_URL',
    'UPLOADER_API_URL',
    'UPLOADER_CLIENT_URL',
    'ZIPPY_URL',
    ];

    const e = {};

    Object.entries(env).forEach(([key, value]) => {
        if (whiteList.includes(key)) {
            e[key] = value;
        }
    })


    return {
        locales: config.available_locales,
        env: e,
    };
});

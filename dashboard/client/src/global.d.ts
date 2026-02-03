import type {WindowConfigBase} from '@alchemy/core';

declare global {
    interface WindowConfig extends WindowConfigBase {
        env: {
            AUTO_CONNECT_IDP: Readonly<string | undefined>;
            DATABOX_API_URL: Readonly<string>;
            DATABOX_CLIENT_URL: Readonly<string>;
            DEV_MODE: Readonly<string>;
            DISPLAY_SERVICES_MENU: Readonly<string>;
            ELASTICHQ_URL: Readonly<string>;
            EXPOSE_API_URL: Readonly<string>;
            EXPOSE_CLIENT_URL: Readonly<string>;
            MAILHOG_URL: Readonly<string>;
            MATOMO_URL: Readonly<string>;
            MATOMO_SITE_ID: Readonly<string>;
            MATOMO_MEDIA_PLUGIN_ENABLED: Readonly<boolean>;
            PGADMIN_URL: Readonly<string>;
            PHPMYADMIN_URL: Readonly<string>;
            RABBITMQ_CONSOLE_URL: Readonly<string>;
            REPORT_API_URL: Readonly<string>;
            SAML2_URL: Readonly<string>;
            SAML_URL: Readonly<string>;
            STACK_NAME: Readonly<string>;
            STACK_VERSION: Readonly<string>;
            TRAEFIK_CONSOLE_URL: Readonly<string>;
            UPLOADER_API_URL: Readonly<string>;
            UPLOADER_CLIENT_URL: Readonly<string>;
            ZIPPY_URL: Readonly<string>;
            SOKETI_USAGE_URL: Readonly<string>;
            NOVU_BRIDGE_URL: Readonly<string>;
        };
    }
}

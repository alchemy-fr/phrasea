declare global {
    interface Window {
        config: {
            locales: string[];
            env: {
                DATABOX_API_URL: string;
                DATABOX_CLIENT_URL: string;
                DEV_MODE: string;
                DISPLAY_SERVICES_MENU: string;
                ELASTICHQ_URL: string;
                EXPOSE_API_URL: string;
                EXPOSE_CLIENT_URL: string;
                KEYCLOAK_URL: string;
                MAILHOG_URL: string;
                MATOMO_URL: string;
                NOTIFY_API_URL: string;
                PGADMIN_URL: string;
                PHPMYADMIN_URL: string;
                RABBITMQ_CONSOLE_URL: string;
                REPORT_API_URL: string;
                SAML2_URL: string;
                SAML_URL: string;
                STACK_NAME: string;
                STACK_VERSION: string;
                TRAEFIK_CONSOLE_URL: string;
                UPLOADER_API_URL: string;
                UPLOADER_CLIENT_URL: string;
                ZIPPY_URL: string;
            };
        };
    }
}

const config = window.config;

export default config;

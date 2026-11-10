import type {ClientThemeMeta} from '@alchemy/next-theme/customTheme';
import type {SentryClientConfig} from '@alchemy/next-core/monitoring/sentry';

/**
 * Runtime configuration of the client. It is resolved on the server at request
 * time from environment variables and the stack configuration (see
 * ./server.ts) and injected into the client tree through the ConfigProvider,
 * so the same Docker image can be deployed against any stack.
 *
 * Everything in it is served to any visitor with the page.
 */
export type AnalyticsConfig = {
    matomo?: {
        baseUrl: string;
        siteId: string;
        /** `MATOMO_DISABLE_COOKIES`: tracks visitors without any cookie */
        disableCookies: boolean;
    };
};

export const appIds = ['databox', 'expose', 'uploader'] as const;

export type AppId = (typeof appIds)[number];

/** A Phrasea application of the stack, deployed when its API URL is set */
export type AppService = {
    apiUrl: string;
    /** Empty when the client is not deployed: the card has no link */
    clientUrl?: string;
};

export const toolIds = [
    'pgadmin',
    'phpmyadmin',
    'mailpit',
    'matomo',
    'rabbitmq',
    'traefik',
    'sockudo',
] as const;

export type ToolId = (typeof toolIds)[number];

export type AppConfig = {
    appId: string;
    clientUrl: string;
    keycloak: {
        url: string;
        realm: string;
        clientId: string;
        /** Identity provider hint to skip the Keycloak IdP chooser */
        autoConnectIdP?: string;
    };
    stack: {
        name?: string;
        version?: string;
    };
    apps: Partial<Record<AppId, AppService>>;
    /** Operation tools offered to the `tech` role, by URL */
    tools: Partial<Record<ToolId, string>>;
    devMode: boolean;
    analytics: AnalyticsConfig;
    /** `SENTRY_DSN`: the browser errors are reported to Sentry */
    sentry?: SentryClientConfig;
    logo?: {
        src: string;
        /** Inline CSS applied to the logo */
        style?: string;
    };
    /**
     * The organisation theme compiled from the stack configuration
     * (`databox.theme`), when one is defined: its stylesheet is served with
     * the page, this is what the theme menu needs.
     */
    theme?: ClientThemeMeta;
};

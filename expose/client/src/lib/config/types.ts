import type {ClientThemeMeta} from '@alchemy/next-theme/customTheme';
import type {SentryClientConfig} from '@alchemy/next-core/monitoring/config';

/**
 * Runtime configuration of the client. It is resolved on the server at request
 * time from environment variables (see ./server.ts) and injected into the
 * client tree through the ConfigProvider, so the same Docker image can be
 * deployed against any stack.
 */
export type AnalyticsConfig = {
    matomo?: {
        baseUrl: string;
        siteId: string;
        mediaPluginEnabled: boolean;
        /** `MATOMO_DISABLE_COOKIES`: tracks visitors without any cookie */
        disableCookies: boolean;
    };
};

export type AppConfig = {
    appId: string;
    clientUrl: string;
    apiUrl: string;
    keycloak: {
        url: string;
        realm: string;
        clientId: string;
        /** Identity provider hint to skip the Keycloak IdP chooser */
        autoConnectIdP?: string;
    };
    dashboardUrl?: string;
    displayServicesMenu: boolean;
    /**
     * `DISABLE_INDEX_PAGE`: no public list of publications at `/`, nor any
     * link to it from the publications
     */
    disableIndexPage: boolean;
    analytics: AnalyticsConfig;
    /** `SENTRY_DSN`: the browser errors are reported to Sentry */
    sentry?: SentryClientConfig;
    logo?: {
        src?: string;
        style?: string;
    };
    /**
     * The organisation theme compiled from the stack configuration
     * (`databox.theme`), when one is defined: its stylesheet is served with
     * the page, this is what the theme menu needs.
     */
    theme?: ClientThemeMeta;
};

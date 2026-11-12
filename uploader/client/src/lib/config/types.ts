import type {ClientThemeMeta} from '@alchemy/next-theme/customTheme';
import type {SentryClientConfig} from '@alchemy/next-core/monitoring/config';

/**
 * Runtime configuration of the client. It is resolved on the server at request
 * time from environment variables and the stack configuration (see
 * ./server.ts), and injected into the client tree through the ConfigProvider,
 * so the same Docker image can be deployed against any stack.
 */
export type UploadConfig = {
    /** Maximum size of a file, in bytes */
    maxFileSize?: number;
    /** Maximum total size of a batch, in bytes */
    maxCommitSize?: number;
    /** Maximum number of files of a batch; 1 = single file mode */
    maxFileCount?: number;
    /** MIME type (may contain a wildcard) => list of allowed extensions */
    allowedTypes: Record<string, string[]>;
};

export type AnalyticsConfig = {
    matomo?: {
        baseUrl: string;
        siteId: string;
        /** `MATOMO_DISABLE_COOKIES`: tracks visitors without any cookie */
        disableCookies: boolean;
    };
};

export type AppConfig = {
    appId: string;
    appName: string;
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
    devMode: boolean;
    analytics: AnalyticsConfig;
    /** `SENTRY_DSN`: the browser errors are reported to Sentry */
    sentry?: SentryClientConfig;
    upload: UploadConfig;
    logo?: {
        src?: string;
        /** Inline CSS applied to the logo (`margin` of `uploader.client.logo`) */
        style?: string;
    };
    /**
     * The organisation theme compiled from the stack configuration, when one
     * is defined: its stylesheet is served with the page, this is what the
     * theme menu needs.
     */
    theme?: ClientThemeMeta;
};

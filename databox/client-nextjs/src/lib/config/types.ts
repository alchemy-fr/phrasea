/**
 * Runtime configuration of the client. It is resolved on the server at request
 * time from environment variables (see ./server.ts) and injected into the
 * client tree through the ConfigProvider, so the same Docker image can be
 * deployed against any stack.
 */
export type UploadConfig = {
    minChunkSize?: number;
    maxChunkSize?: number;
    maxPartNumber?: number;
    maxFileSize?: number;
    /** MIME type (may contain a wildcard) => list of allowed extensions */
    allowedTypes: Record<string, string[]>;
};

export type AnalyticsConfig = {
    matomo?: {
        baseUrl: string;
        siteId: string;
        mediaPluginEnabled: boolean;
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
    requestSignatureTtl?: number;
    realtime?: {
        host: string;
        key: string;
    };
    notifications: boolean;
    analytics: AnalyticsConfig;
    sentry?: {
        dsn: string;
        environment?: string;
        release?: string;
    };
    upload: UploadConfig;
    logo?: {
        src?: string;
        style?: string;
    };
};

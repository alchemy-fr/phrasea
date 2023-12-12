export type AnalyticsConfig = {
    matomo?: {
        baseUrl: string;
        siteId: string;
    };
};

export type WindowConfig = {
    autoConnectIdP: Readonly<string | undefined>;
    sentryDsn?: Readonly<string | undefined>;
    sentryEnvironment: Readonly<string>;
    sentryRelease: Readonly<string>;
    appId: Readonly<string>;
    appName: string;
    locales: Readonly<string[]>;
    baseUrl: Readonly<string>;
    keycloakUrl: Readonly<string>;
    realmName: Readonly<string>;
    clientId: Readonly<string>;
    displayServicesMenu: Readonly<boolean>;
    dashboardBaseUrl: Readonly<string>;
    devMode: Readonly<boolean>;
    analytics?: AnalyticsConfig;
}

export type SentryConfig = Pick<WindowConfig,
    "sentryDsn" |
    "sentryEnvironment" |
    "sentryRelease" |
    "appId" |
    "appName"
>

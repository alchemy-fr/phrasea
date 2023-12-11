export type AnalyticsConfig = {
    matomo?: {
        baseUrl: string;
        siteId: string;
    };
};

declare global {
    type Config = {
        analytics?: AnalyticsConfig;
        locales: Readonly<string[]>;
        autoConnectIdP: Readonly<string | undefined>;
        baseUrl: Readonly<string>;
        keycloakUrl: Readonly<string>;
        realmName: Readonly<string>;
        clientId: Readonly<string>;
        displayServicesMenu: Readonly<boolean>;
        requestSignatureTtl: Readonly<string>;
        disableIndexPage?: Readonly<boolean>;
        dashboardBaseUrl: Readonly<string>;
        globalCSS: Readonly<string | undefined>;
        zippyEnabled: Readonly<boolean | undefined>;
    };

    interface Window {
        config: Config;
    }
}

const config = window.config;

export default config;

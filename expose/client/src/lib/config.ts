export type AnalyticsConfig = {
    matomo?: {
        baseUrl: string;
        siteId: string;
    };
};

declare global {
    type Config = {
        analytics?: AnalyticsConfig;
        locales: string[];
        autoConnectIdP: string | undefined | null;
        baseUrl: string;
        keycloakUrl: string;
        realmName: string;
        clientId: string;
        displayServicesMenu: string;
        requestSignatureTtl: string;
        disableIndexPage: string;
        dashboardBaseUrl: string;
        globalCSS: string | undefined;
        zippyEnabled?: boolean;
    };

    interface Window {
        config: Config;
    }
}

const config = window.config;

export default config;

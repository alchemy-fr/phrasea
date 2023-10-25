export type AnalyticsConfig = {
    matomo?: {
        baseUrl: string;
        siteId: string;
    }
}

declare global {
    interface Window {
        config: {
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
        };
    }
}

const config = window.config;

export default config;


declare global {
    interface Window {
        config: {
            locales: string[];
            autoConnectIdP: string | undefined | null;
            baseUrl: string;
            keycloakUrl: string;
            realmName: string;
            clientId: string;
            requestSignatureTtl: string;
            disableIndexPage: string;
            dashboardBaseUrl: string;
            globalCSS: string | undefined;
        };
    }
}

const config = window.config;

export default config;

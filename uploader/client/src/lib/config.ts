import {Accept} from 'react-dropzone';

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
        autoConnectIdP: string | undefined;
        baseUrl: string;
        keycloakUrl: string;
        realmName: string;
        clientId: string;
        displayServicesMenu: boolean;
        requestSignatureTtl: string;
        disableIndexPage: string;
        dashboardBaseUrl: string;
        globalCSS: string | undefined;
        zippyEnabled?: boolean;
        maxFileSize: number;
        maxCommitSize: number;
        maxFileCount: number;
        client?: {
            logo?: {
                src: string;
                margin?: string;
            };
        };
        devMode?: boolean;
        allowedTypes: Readonly<Accept | undefined>;
    };

    interface Window {
        config: Config;
    }
}

const config = window.config;

export default config;

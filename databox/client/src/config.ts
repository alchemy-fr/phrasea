import {Accept} from 'react-dropzone';

declare global {
    interface Window {
        config: {
            locales: Readonly<string[]>;
            autoConnectIdP: Readonly<string | undefined>;
            baseUrl: Readonly<string>;
            uploaderApiBaseUrl: Readonly<string>;
            uploaderTargetSlug: Readonly<string>;
            keycloakUrl: Readonly<string>;
            realmName: Readonly<string>;
            clientId: Readonly<string>;
            devMode: Readonly<boolean>;
            requestSignatureTtl: Readonly<string>;
            displayServicesMenu: Readonly<boolean>;
            dashboardBaseUrl: Readonly<string>;
            allowedTypes: Readonly<Accept | undefined>;
        };
    }
}

const config = window.config;

export default config;

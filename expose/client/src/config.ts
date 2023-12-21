import {WindowConfig} from '@alchemy/core';

declare global {
    interface Window {
        config: {
            clientId: Readonly<string>;
            requestSignatureTtl: Readonly<string>;
            disableIndexPage?: Readonly<boolean>;
            dashboardBaseUrl: Readonly<string>;
            globalCSS: Readonly<string | undefined>;
            zippyEnabled: Readonly<boolean | undefined>;
        } & WindowConfig;
    }
}

const config = window.config;
config.appName = 'expose';

export default config;

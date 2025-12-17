import {WindowConfig} from '@alchemy/core';

declare global {
    interface Window {
        config: {
            requestSignatureTtl: Readonly<string>;
            disableIndexPage?: Readonly<boolean>;
            zippyEnabled: Readonly<boolean | undefined>;
        } & WindowConfig;
    }
}

const config = window.config;
config.appName = 'expose';

export default config;

import {Accept} from 'react-dropzone';
import {WindowConfig} from '@alchemy/core';

declare global {
    interface Window {
        config: {
            uploaderApiBaseUrl: Readonly<string>;
            uploaderTargetSlug: Readonly<string>;
            requestSignatureTtl: Readonly<string>;
            allowedTypes: Readonly<Accept | undefined>;
        } & WindowConfig;
    }
}

const config = window.config;
config.appName = 'databox';

export default config;

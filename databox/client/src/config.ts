import {Accept} from 'react-dropzone';
import {WindowConfigBase} from '@alchemy/core';

declare global {
    interface WindowConfig extends WindowConfigBase {
        uploaderApiBaseUrl: Readonly<string>;
        uploaderTargetSlug: Readonly<string>;
        requestSignatureTtl: Readonly<string>;
        allowedTypes: Readonly<Accept | undefined>;
    }
}

const config = window.config;
config.appName = 'databox';

export default config;

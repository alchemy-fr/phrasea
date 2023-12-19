import {Accept} from 'react-dropzone';
import {WindowConfig} from '@alchemy/core';

declare global {
    interface Window {
        config: {
            requestSignatureTtl: string;
            disableIndexPage: string;
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
            allowedTypes: Readonly<Accept | undefined>;
        } & WindowConfig;
    }
}

const config = window.config;
config.appName = 'uploader';

export default config;

import {UploadConfig, WindowConfigBase} from '@alchemy/core';

declare global {
    interface WindowConfig extends WindowConfigBase {
        requestSignatureTtl: Readonly<string>;
        disableIndexPage: Readonly<string>;
        globalCSS: Readonly<string | undefined>;
        zippyEnabled?: Readonly<boolean>;
        maxCommitSize: Readonly<number>;
        maxFileCount: Readonly<number>;
        client?: {
            logo?: {
                src: Readonly<string>;
                margin?: Readonly<string>;
            };
        };
        upload: UploadConfig;
    }
}

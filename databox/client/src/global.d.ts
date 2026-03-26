import {UploadConfig, WindowConfigBase} from '@alchemy/core';

declare global {
    interface WindowConfig extends WindowConfigBase {
        requestSignatureTtl: Readonly<string>;
        upload: UploadConfig;
    }
}

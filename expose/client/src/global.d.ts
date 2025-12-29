import type {WindowConfigBase} from '@alchemy/core';

declare global {
    interface WindowConfig extends WindowConfigBase {
        requestSignatureTtl: Readonly<string>;
        disableIndexPage: Readonly<boolean | undefined>;
        zippyEnabled: Readonly<boolean | undefined>;
    }
}

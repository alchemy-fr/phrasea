import type {WindowConfigBase} from '@alchemy/core';
import {Accept} from 'react-dropzone';

declare global {
    interface WindowConfig extends WindowConfigBase {
        requestSignatureTtl: Readonly<string>;
        allowedTypes: Readonly<Accept | undefined>;
    }
}

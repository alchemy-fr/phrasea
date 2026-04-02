import {Accept} from 'react-dropzone';
import React from 'react';
import {config} from '../../init.ts';

export function useAccept(): Accept | undefined {
    return React.useMemo<Accept | undefined>(() => {
        const a = config.upload.allowedTypes;
        if (!a) {
            return;
        }

        const n = {...a};
        try {
            Object.keys(n).forEach(k => {
                n[k] = n[k].map(e => `.${e.replace(/^\./, '')}`);
                if (n[k].length === 0) {
                    throw new Error(
                        `Missing extension list for MIME type ${k}`
                    );
                }
            });
        } catch (e: any) {
            // eslint-disable-next-line no-console
            console.error(e.toString());

            return;
        }

        return n;
    }, []);
}

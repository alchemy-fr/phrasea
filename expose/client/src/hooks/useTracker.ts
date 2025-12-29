import {Asset} from '../types.ts';
import {MutableRefObject, useEffect} from 'react';
import {FileTypeEnum, getFileTypeFromMIMEType} from '@alchemy/core';
import {useMatomo} from '@jonkoops/matomo-tracker-react';

type Props = {
    asset: Asset;
    containerRef: MutableRefObject<HTMLElement | null>;
};

export function useTracker({asset, containerRef}: Props) {
    const {pushInstruction} = useMatomo();

    useEffect(() => {
        const type = getFileTypeFromMIMEType(asset.mimeType);

        if (containerRef.current) {
            // TODO
            // if (asset.assetId) {
            //     pushInstruction('trackContentImpression', asset.title ?? asset.id, asset.assetId);
            // }

            if ([FileTypeEnum.Audio, FileTypeEnum.Video].includes(type)) {
                if (process.env.NODE_ENV !== 'production') {
                    pushInstruction('MediaAnalytics::enableDebugMode');
                }

                pushInstruction('MediaAnalytics::setPingInterval', 10);
                pushInstruction(
                    'MediaAnalytics::scanForMedia',
                    containerRef.current
                );
            }
        }
    }, [asset, containerRef]);
}

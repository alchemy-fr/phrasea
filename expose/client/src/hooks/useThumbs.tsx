import {Asset, Thumb} from '../types.ts';
import {useMemo} from 'react';
import {routes} from '../routes.ts';
import {getPath} from '@alchemy/navigation';

type Props = {
    publicationId: string;
    assets: Asset[];
};

export function useThumbs({publicationId, assets}: Props): Thumb[] {
    return useMemo(() => {
        return assets.map(a => ({
            id: a.id,
            src: a.thumbUrl || undefined,
            mimeType: a.mimeType,
            alt: a.title || 'Image',
            path: getPath(routes.publication.routes.asset, {
                id: publicationId,
                assetId: a.id,
            }),
        }));
    }, [publicationId, assets]);
}

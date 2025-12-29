import {Asset, Publication, Thumb} from '../types.ts';
import {useMemo} from 'react';
import {routes} from '../routes.ts';
import {getPath} from '@alchemy/navigation';

type Props = {
    publication: Publication;
    assets: Asset[];
};

export function useThumbs({publication, assets}: Props): Thumb[] {
    return useMemo(() => {
        return assets.map(a => ({
            id: a.id,
            src: a.thumbUrl || undefined,
            mimeType: a.mimeType,
            alt: a.title || 'Image',
            path: getPath(routes.publication.routes.asset, {
                id: publication.slug || publication.id,
                assetId: a.id,
            }),
        }));
    }, [publication, assets]);
}

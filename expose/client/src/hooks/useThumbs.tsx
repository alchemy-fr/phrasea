import {Asset, Publication, Thumb} from '../types.ts';
import {useMemo} from 'react';
import {getPublicationAssetPath} from './useNavigateToPublication.ts';

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
            path: getPublicationAssetPath(publication, a.id),
        }));
    }, [publication, assets]);
}

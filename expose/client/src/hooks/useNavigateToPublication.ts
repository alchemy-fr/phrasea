import {getPath, useNavigate} from '@alchemy/navigation';
import {Publication} from '../types.ts';
import {routes} from '../routes.ts';
import {useCallback} from 'react';

export function useNavigateToPublication() {
    const navigate = useNavigate();

    return useCallback(
        (publication: Publication, assetId?: string) => {
            if (assetId) {
                navigate(getPublicationAssetPath(publication, assetId));
            } else {
                navigate(getPublicationPath(publication));
            }
        },
        [navigate]
    );
}

export function getPublicationPath(publication: Publication) {
    return getPath(routes.publicationView, {
        id: publication.slug || publication.id,
    });
}

export function getPublicationAssetPath(
    publication: Publication,
    assetId: string
) {
    return getPath(routes.publicationView.routes.asset, {
        id: publication.slug || publication.id,
        assetId: assetId,
    });
}

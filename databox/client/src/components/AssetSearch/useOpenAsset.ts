import {useCallback} from 'react';
import {OnOpen} from '../AssetList/types.ts';
import {modalRoutes, Routing} from '../../routes.ts';
import {AssetContextState} from '../Media/Asset/assetTypes.ts';
import {useNavigateToModal} from '../Routing/ModalLink.tsx';
import {TResultContext} from '../Media/Search/ResultContext.tsx';

type Props = {
    resultContext?: TResultContext;
};

export function useOpenAsset({resultContext}: Props) {
    const navigateToModal = useNavigateToModal();

    return useCallback<OnOpen>(
        (asset, renditionId, storyAssetId): void => {
            if (!renditionId) {
                renditionId = asset.original?.id;
            }

            navigateToModal(
                modalRoutes.assets.routes.view,
                {
                    id: asset.id,
                    renditionId: renditionId || Routing.UnknownRendition,
                },
                {
                    state: {
                        storyAssetId,
                        assetsContext: resultContext?.pages
                            .flat()
                            .map(a => [
                                a.id,
                                a.original?.id,
                            ]) as AssetContextState,
                    },
                }
            );
            // eslint-disable-next-line
        },
        [navigateToModal, resultContext]
    );
}

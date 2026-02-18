import {useCallback} from 'react';
import {OnOpen} from '../AssetList/types.ts';
import {modalRoutes, Routing} from '../../routes.ts';
import {AssetContextState} from '../Media/Asset/assetTypes.ts';
import {useNavigateToModal} from '../Routing/ModalLink.tsx';
import {Asset} from '../../types.ts';
import {TResultContext} from '../Media/Search/ResultContext.tsx';

type Props =
    | {
          assets: Asset[];
          resultContext?: never;
      }
    | {
          resultContext?: TResultContext;
          assets?: never;
      };

export function useOpenAsset({assets, resultContext}: Props) {
    const navigateToModal = useNavigateToModal();

    return useCallback<OnOpen>(
        (asset, renditionId, storyAssetId): void => {
            if (!renditionId) {
                renditionId = asset.main?.id;
            }

            const contextAssets = assets ?? resultContext?.pages.flat();

            navigateToModal(
                modalRoutes.assets.routes.view,
                {
                    id: asset.id,
                    renditionId: renditionId || Routing.UnknownRendition,
                },
                {
                    state: {
                        storyAssetId,
                        assetsContext: contextAssets?.map(a => [
                            a.id,
                            a.main?.id,
                        ]) as AssetContextState,
                    },
                }
            );
            // eslint-disable-next-line
        },
        [navigateToModal, assets, resultContext]
    );
}

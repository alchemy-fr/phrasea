import {useNavigateToModal} from '../components/Routing/ModalLink.tsx';
import {useMemo} from 'react';
import DeleteAssetsConfirm from '../components/Media/Asset/Actions/DeleteAssetsConfirm.tsx';
import ExportAssetsDialog from '../components/Media/Asset/Actions/ExportAssetsDialog.tsx';
import {modalRoutes, Routing} from '../routes.ts';
import {Asset, AssetOrAssetContainer} from '../types.ts';
import {useModals} from '@alchemy/navigation';
import {ActionsContext, ReloadFunc} from '../components/AssetList/types.ts';
import {createDefaultActionsContext} from '../components/AssetList/actionContext.ts';
import MoveAssetsDialog from '../components/Media/Asset/Actions/MoveAssetsDialog.tsx';
import CopyAssetsDialog from '../components/Media/Asset/Actions/CopyAssetsDialog.tsx';
import ReplaceAssetSourceDialog from '../components/Media/Asset/Actions/ReplaceAssetSourceDialog.tsx';
import ShareAssetDialog from '../components/Share/ShareAssetDialog.tsx';
import RestoreAssetsConfirm from '../components/Media/Asset/Actions/RestoreAssetsConfirm.tsx';

type Props<Item extends AssetOrAssetContainer> = {
    asset: Asset;
    onAction?: () => void;
    actionsContext?: ActionsContext<Item>;
    onDelete?: () => void;
    onRestore?: () => void;
    reload?: ReloadFunc;
};

export function useAssetActions<Item extends AssetOrAssetContainer>({
    asset,
    onAction,
    actionsContext = createDefaultActionsContext(),
    onDelete,
    onRestore,
    reload,
}: Props<Item>) {
    const {openModal} = useModals();
    const navigateToModal = useNavigateToModal();
    const {id, main, capabilities} = asset;

    return useMemo(
        () => ({
            can: {
                open: actionsContext.open && (main || asset.storyCollection),
                saveAs: actionsContext.saveAs && asset.source,
                download: actionsContext.export && main?.file?.url,
                edit: actionsContext.edit && capabilities.canEdit,
                editAttributes:
                    actionsContext.edit && capabilities.canEditAttributes,
                delete:
                    actionsContext.delete &&
                    capabilities.canDelete &&
                    !asset.deleted,
                restore:
                    actionsContext.restore &&
                    capabilities.canDelete &&
                    asset.deleted,
                share: capabilities.canShare,
                substitute: capabilities.canEdit,
            },
            onDownload: () => {
                openModal(ExportAssetsDialog, {
                    assets: [asset],
                });
                onAction?.();
            },
            onSubstituteFile: () => {
                openModal(ReplaceAssetSourceDialog, {
                    asset,
                });
                onAction?.();
            },
            onDelete: () => {
                openModal(DeleteAssetsConfirm, {
                    assetIds: [id],
                    onDelete,
                });
                onAction?.();
            },
            onRestore: () => {
                openModal(RestoreAssetsConfirm, {
                    assets: [asset],
                    onRestore,
                });
                onAction?.();
            },
            onOpen: (renditionId?: string) => {
                if (!renditionId) {
                    renditionId = asset.main?.id;
                }
                navigateToModal(modalRoutes.assets.routes.view, {
                    id: asset.id,
                    renditionId: renditionId || Routing.UnknownRendition,
                });
                onAction?.();
            },
            onEdit: () => {
                navigateToModal(modalRoutes.assets.routes.manage, {
                    tab: 'edit',
                    id: asset.id,
                });
                onAction?.();
            },
            onMove: () => {
                openModal(MoveAssetsDialog, {
                    assetIds: [asset.id],
                    workspaceId: asset.workspace.id,
                    onComplete: () => {
                        reload?.();
                    },
                });
                onAction?.();
            },
            onCopy: () => {
                openModal(CopyAssetsDialog, {
                    assets: [asset],
                    onComplete: () => {
                        reload?.();
                    },
                });
                onAction?.();
            },
            onShare: () => {
                openModal(ShareAssetDialog, {
                    asset,
                });
                onAction?.();
            },
            onReplace: () => {
                openModal(ReplaceAssetSourceDialog, {
                    asset,
                });
                onAction?.();
            },
            onInfo: () => {
                navigateToModal(modalRoutes.assets.routes.manage, {
                    tab: 'info',
                    id: asset.id,
                });
                onAction?.();
            },
            onEditAttr: () => {
                navigateToModal(modalRoutes.assets.routes.manage, {
                    tab: 'attributes',
                    id: asset.id,
                });
                onAction?.();
            },
        }),
        [asset.id]
    );
}

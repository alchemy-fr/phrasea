import {useNavigateToModal} from "../components/Routing/ModalLink.tsx";
import {useMemo} from "react";
import DeleteAssetsConfirm from "../components/Media/Asset/Actions/DeleteAssetsConfirm.tsx";
import ExportAssetsDialog from "../components/Media/Asset/Actions/ExportAssetsDialog.tsx";
import {modalRoutes} from "../routes.ts";
import {Asset, AssetOrAssetContainer} from "../types.ts";
import {useModals} from '@alchemy/navigation';
import {ActionsContext} from "../components/AssetList/types.ts";
import {createDefaultActionsContext} from "../components/AssetList/actionContext.ts";
import SubstituteFileDialog from "../components/Media/Asset/Actions/SubstituteFileDialog.tsx";

type Props<Item extends AssetOrAssetContainer> = {
    asset: Asset;
    onAction?: () => void;
    actionsContext?: ActionsContext<Item>;
    onDelete?: () => void;
}

export function useAssetActions<Item extends AssetOrAssetContainer>({
    asset,
    onAction,
    actionsContext = createDefaultActionsContext(),
    onDelete,
}: Props<Item>) {
    const {openModal} = useModals();
    const navigateToModal = useNavigateToModal();
    const {id, original, capabilities} = asset;

    return useMemo(() => ({
        can: {
            open: actionsContext.open && original,
            saveAs: actionsContext.saveAs && asset.source,
            download: actionsContext.export && original?.file?.url,
            edit: actionsContext.edit && capabilities.canEdit,
            editAttributes: actionsContext.edit && capabilities.canEditAttributes,
            delete: actionsContext.delete && capabilities.canDelete,
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
            openModal(SubstituteFileDialog, {
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
        onOpen: (renditionId: string) => {
            navigateToModal(modalRoutes.assets.routes.view, {
                id: asset.id,
                renditionId,
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
        onEditAttr: () => {
            navigateToModal(modalRoutes.assets.routes.manage, {
                tab: 'attributes',
                id: asset.id,
            });
            onAction?.();
        },
    }), [asset.id]);
}

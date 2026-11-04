'use client';

import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
import type {ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {patchAsset} from '@/lib/api/assets';
import {useAssetStore} from '@/features/assets/assetStore';
import {ClipEditorDialog} from '@/features/clip/ClipEditorDialog';

/**
 * Region of interest of an asset: time ranges and crop of its source file,
 * shown by default, and applied when the asset is published or exported.
 */
export function AssetRoiDialog({
    asset,
    ...modalProps
}: ModalProps<boolean> & {asset: Asset}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const update = useAssetStore(s => s.update);

    return (
        <ClipEditorDialog
            {...modalProps}
            asset={asset}
            initial={asset.roi}
            title={t('asset.roi.dialog.title', 'Region of interest')}
            description={t(
                'asset.roi.dialog.description',
                'The file is not modified: the region of interest is shown by default, and applied when the asset is published or exported. A basket item can replace it with its own extract.'
            )}
            removeLabel={t('asset.roi.remove', 'Remove the region of interest')}
            onSave={async roi => {
                const updated = await patchAsset(asset.id, {roi});
                update(updated);
                void queryClient.invalidateQueries({
                    queryKey: ['asset-view', asset.id],
                });
                toast.success(
                    roi
                        ? t('asset.roi.saved', 'Region of interest saved')
                        : t('asset.roi.removed', 'Region of interest removed')
                );
            }}
        />
    );
}

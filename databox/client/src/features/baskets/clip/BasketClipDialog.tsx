'use client';

import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
import type {ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {patchBasketAssetClip} from '@/lib/api/misc';
import {ClipEditorDialog} from '@/features/clip/ClipEditorDialog';

/**
 * Time ranges and crop of a basket item, in place of the region of interest
 * of its asset. An item without an extract starts from that region.
 */
export function BasketClipDialog({
    asset,
    ...modalProps
}: ModalProps<boolean> & {asset: Asset}) {
    const {t} = useTranslation();
    const itemId = asset.basketItemId!;
    const own = asset.basketClip;
    const inherited = !own && asset.roi ? asset.roi : undefined;

    return (
        <ClipEditorDialog
            {...modalProps}
            asset={asset}
            initial={own ?? inherited}
            removable={!!own}
            title={t('basket.clip.dialog.title', 'Cut / crop')}
            description={t(
                'basket.clip.dialog.description',
                'The file is not modified: the extract applies to this basket item when it is played, published or exported.'
            )}
            notice={
                inherited
                    ? t(
                          'basket.clip.dialog.inherited',
                          'This item inherits the region of interest of the asset. Saving makes it an extract of its own, which replaces that region.'
                      )
                    : undefined
            }
            removeLabel={t('basket.clip.remove', 'Remove extract')}
            onSave={async clip => {
                await patchBasketAssetClip(itemId, clip);
                toast.success(
                    clip
                        ? t('basket.clip.saved', 'Extract saved')
                        : t('basket.clip.removed', 'Extract removed')
                );
            }}
        />
    );
}

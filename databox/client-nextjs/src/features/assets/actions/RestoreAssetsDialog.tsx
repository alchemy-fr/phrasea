'use client';

import {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {AlertTriangleIcon, RotateCcwIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset, Collection} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {useModals} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Button} from '@/components/ui/button';
import {Alert} from '@/components/ui/misc';
import {CollectionChip} from '@/components/chips';
import {restoreAssets} from '@/lib/api/assets';
import {RestoreCollectionDialog} from '@/features/collections/CollectionDialogs';

type Props = ModalProps<string[]> & {assets: Asset[]; onComplete?: () => void};

/**
 * Restores trashed assets. Assets whose reference collection is itself deleted
 * cannot be restored until the collection is.
 */
export function RestoreAssetsDialog({
    open,
    onOpenChange,
    resolve,
    assets,
    onComplete,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();

    const restorable = useMemo(
        () =>
            assets.filter(
                a =>
                    !a.referenceCollection?.deleted ||
                    assets.some(
                        s => s.id === a.referenceCollection?.storyAsset?.id
                    )
            ),
        [assets]
    );
    const blockedCollections = useMemo(() => {
        const map = new Map<string, Collection>();
        assets.forEach(a => {
            const c = a.referenceCollection;
            if (c?.deleted && !assets.some(s => s.id === c.storyAsset?.id)) {
                map.set(c.id, c);
            }
        });

        return [...map.values()];
    }, [assets]);

    const submit = async () => {
        const ids = restorable.map(a => a.id);
        await restoreAssets(ids);
        toast.success(
            t('asset.restore.done', '{{count}} asset(s) restored', {
                count: ids.length,
            })
        );
        onComplete?.();
        resolve?.(ids);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('asset.restore.title', 'Restore {{count}} asset(s)?', {
                count: restorable.length,
            })}
            description={t(
                'asset.restore.description',
                'Assets will be moved back to their collections.'
            )}
            submitLabel={t('common.restore', 'Restore')}
            submitIcon={<RotateCcwIcon />}
            canSubmit={restorable.length > 0}
            onSubmit={submit}
        >
            {blockedCollections.length > 0 ? (
                <Alert
                    variant="warning"
                    icon={<AlertTriangleIcon />}
                    title={t(
                        'asset.restore.blocked_title',
                        'Some assets cannot be restored'
                    )}
                >
                    <p className="mb-2">
                        {t(
                            'asset.restore.blocked_help',
                            'Their collection is deleted. Restore the collection first:'
                        )}
                    </p>
                    <ul className="space-y-1">
                        {blockedCollections.map(c => (
                            <li
                                key={c.id}
                                className="flex items-center justify-between gap-2"
                            >
                                <CollectionChip
                                    collection={c}
                                    absolute
                                    size="sm"
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        openModal(RestoreCollectionDialog, {
                                            collection: c,
                                        })
                                    }
                                >
                                    <RotateCcwIcon />{' '}
                                    {t('common.restore', 'Restore')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                </Alert>
            ) : null}
        </FormDialog>
    );
}

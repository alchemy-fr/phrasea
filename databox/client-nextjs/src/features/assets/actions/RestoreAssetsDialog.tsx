'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {AlertTriangleIcon, RotateCcwIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset, Collection} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {useModals} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {Alert} from '@/components/ui/misc';
import {CollectionChip} from '@/components/chips';
import {restoreAssets} from '@/lib/api/assets';
import {RestoreCollectionDialog} from '@/features/collections/CollectionDialogs';

type Props = ModalProps & {assets: Asset[]; onComplete?: () => void};

/**
 * Restores trashed assets. Assets whose reference collection is itself deleted
 * cannot be restored until the collection is.
 */
export function RestoreAssetsDialog({
    open,
    onOpenChange,
    assets,
    onComplete,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [loading, setLoading] = useState(false);

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
        setLoading(true);
        try {
            await restoreAssets(restorable.map(a => a.id));
            toast.success(
                t('asset.restore.done', '{{count}} asset(s) restored', {
                    count: restorable.length,
                })
            );
            onComplete?.();
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {t(
                            'asset.restore.title',
                            'Restore {{count}} asset(s)?',
                            {count: restorable.length}
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'asset.restore.description',
                            'Assets will be moved back to their collections.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                {blockedCollections.length > 0 ? (
                    <DialogBody>
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
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                openModal(
                                                    RestoreCollectionDialog,
                                                    {collection: c}
                                                )
                                            }
                                        >
                                            <RotateCcwIcon />{' '}
                                            {t('common.restore', 'Restore')}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </Alert>
                    </DialogBody>
                ) : null}
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={restorable.length === 0}
                        loading={loading}
                    >
                        <RotateCcwIcon /> {t('common.restore', 'Restore')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

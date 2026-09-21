'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {CopyCheckIcon, ShieldCheckIcon, Trash2Icon} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset, DuplicateAsset} from '@/types/api';
import {Button} from '@/components/ui/button';
import {bypassQuarantine} from '@/lib/api/assets';
import {useAssetStore} from '@/features/assets/assetStore';
import {useModals} from '@/components/modals/ModalProvider';
import {DeleteAssetsDialog} from '@/features/assets/actions/DeleteAssetsDialog';
import {MergeDuplicatesDialog} from './MergeDuplicatesDialog';
import {quarantineQueueKey, useQuarantineQueueStore} from './quarantineQueue';

/**
 * The four ways out of quarantine: accept the file, merge it into an existing
 * duplicate, trash it or delete it for good.
 *
 * Whatever the way out, the asset is dropped from the quarantine queue and
 * `onResolved` is called, so that the queue can move on to the next one.
 */
export function QuarantineActions({
    asset,
    duplicates,
    onResolved,
}: {
    asset: Asset;
    duplicates?: DuplicateAsset[];
    onResolved?: () => void;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const update = useAssetStore(s => s.update);
    const markResolved = useQuarantineQueueStore(s => s.markResolved);
    const [bypassing, setBypassing] = useState(false);

    const resolved = () => {
        markResolved(asset.id);
        void queryClient.invalidateQueries({queryKey: quarantineQueueKey});
        onResolved?.();
    };

    const bypass = async () => {
        setBypassing(true);
        try {
            const updated = await bypassQuarantine(asset.id);
            update(updated);
            toast.success(t('quarantine.bypassed', 'Asset accepted'));
            resolved();
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setBypassing(false);
        }
    };

    return (
        <div className="flex flex-wrap gap-2">
            <Button
                size="sm"
                variant="outline"
                data-testid="quarantine-bypass"
                onClick={bypass}
                loading={bypassing}
            >
                <ShieldCheckIcon /> {t('quarantine.bypass', 'Bypass')}
            </Button>
            {duplicates && duplicates.length > 0 ? (
                <Button
                    size="sm"
                    variant="outline"
                    data-testid="quarantine-merge"
                    onClick={() =>
                        openModal(MergeDuplicatesDialog, {
                            asset,
                            duplicates,
                            onComplete: resolved,
                        })
                    }
                >
                    <CopyCheckIcon />{' '}
                    {t('quarantine.merge', 'Merge duplicates')}
                </Button>
            ) : null}
            <Button
                size="sm"
                variant="outline"
                onClick={() =>
                    openModal(DeleteAssetsDialog, {
                        assets: [asset],
                        onComplete: resolved,
                    })
                }
            >
                <Trash2Icon /> {t('quarantine.trash', 'Move to trash')}
            </Button>
            <Button
                size="sm"
                variant="destructive"
                onClick={() =>
                    openModal(DeleteAssetsDialog, {
                        assets: [asset],
                        hardDelete: true,
                        onComplete: resolved,
                    })
                }
            >
                <Trash2Icon />{' '}
                {t('asset.actions.delete_permanently', 'Delete permanently')}
            </Button>
        </div>
    );
}

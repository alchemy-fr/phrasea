'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {FolderInputIcon, RotateCcwIcon, Trash2Icon} from 'lucide-react';
import {toast} from 'sonner';
import type {CollectionTabProps} from '../CollectionManageRoute';
import {Button} from '@/components/ui/button';
import {Alert} from '@/components/ui/misc';
import {
    CollectionTreePicker,
    TreeSelection,
} from '@/components/form/CollectionTreePicker';
import {moveCollection} from '@/lib/api/collections';
import {useCollectionStore} from '../../collectionStore';
import {useModals} from '@/components/modals/ModalProvider';
import {
    DeleteCollectionDialog,
    RestoreCollectionDialog,
} from '../../CollectionDialogs';

export function CollectionOperationsTab({
    collection,
    refresh,
    onClose,
}: CollectionTabProps & {onClose: () => void}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const move = useCollectionStore(s => s.moveCollection);
    const [destination, setDestination] = useState<TreeSelection>();
    const [moving, setMoving] = useState(false);

    const doMove = async () => {
        if (!destination) {
            return;
        }
        setMoving(true);
        try {
            await moveCollection(collection.id, destination.collectionId);
            move(collection.id, destination.collectionId);
            refresh();
            toast.success(t('collection.move.done', 'Collection moved'));
            setDestination(undefined);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setMoving(false);
        }
    };

    return (
        <div className="max-w-2xl space-y-6">
            {collection.capabilities.edit ? (
                <section className="space-y-2">
                    <h3 className="text-sm font-semibold">
                        {t('collection.move.title', 'Move collection')}
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'collection.move.help',
                            'Select the new parent (the workspace itself moves the collection to the root).'
                        )}
                    </p>
                    <CollectionTreePicker
                        value={destination}
                        onChange={setDestination}
                        workspaceId={collection.workspace.id}
                        requireCapability="createCollection"
                        disabledIds={[collection.id]}
                    />
                    <Button
                        onClick={doMove}
                        disabled={!destination}
                        loading={moving}
                    >
                        <FolderInputIcon /> {t('asset.actions.move', 'Move')}
                    </Button>
                </section>
            ) : null}
            {collection.capabilities.delete ? (
                <Alert
                    variant="destructive"
                    title={t('asset.ops.danger_zone', 'Danger zone')}
                >
                    <div className="mt-2 flex gap-2">
                        {collection.deleted ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    openModal(RestoreCollectionDialog, {
                                        collection,
                                        onRestored: refresh,
                                    })
                                }
                            >
                                <RotateCcwIcon />{' '}
                                {t('common.restore', 'Restore')}
                            </Button>
                        ) : (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() =>
                                    openModal(DeleteCollectionDialog, {
                                        collection,
                                        onDeleted: onClose,
                                    })
                                }
                            >
                                <Trash2Icon /> {t('common.delete', 'Delete')}
                            </Button>
                        )}
                    </div>
                </Alert>
            ) : null}
        </div>
    );
}

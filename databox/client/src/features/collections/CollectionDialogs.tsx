'use client';

import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import type {Collection} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {deleteCollections, restoreCollections} from '@/lib/api/collections';
import {useCollectionStore} from './collectionStore';

export function DeleteCollectionDialog({
    collection,
    onDeleted,
    ...modal
}: ModalProps & {collection: Collection; onDeleted?: () => void}) {
    const {t} = useTranslation();
    const patch = useCollectionStore(s => s.patchCollections);

    return (
        <ConfirmDialog
            {...modal}
            destructive
            title={t(
                'collections.delete.title',
                'Delete collection "{{name}}"?',
                {name: collection.displayName ?? collection.name}
            )}
            description={t(
                'collections.delete.description',
                'The collection and its assets will be moved to the trash.'
            )}
            textToType={collection.displayName ?? collection.name}
            confirmLabel={t('common.delete', 'Delete')}
            onConfirm={async () => {
                await deleteCollections([collection.id]);
                patch([collection.id], {deleted: true});
                toast.success(
                    t('collections.deleted', 'Collection moved to trash')
                );
                onDeleted?.();
            }}
        />
    );
}

export function RestoreCollectionDialog({
    collection,
    onRestored,
    ...modal
}: ModalProps & {collection: Collection; onRestored?: () => void}) {
    const {t} = useTranslation();
    const patch = useCollectionStore(s => s.patchCollections);

    return (
        <ConfirmDialog
            {...modal}
            title={t(
                'collections.restore.title',
                'Restore collection "{{name}}"?',
                {name: collection.displayName ?? collection.name}
            )}
            confirmLabel={t('common.restore', 'Restore')}
            onConfirm={async () => {
                await restoreCollections([collection.id]);
                patch([collection.id], {deleted: false});
                toast.success(t('collections.restored', 'Collection restored'));
                onRestored?.();
            }}
        />
    );
}

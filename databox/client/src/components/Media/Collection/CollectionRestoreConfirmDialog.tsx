import {ConfirmDialog} from '@alchemy/phrasea-framework';
import {Trans, useTranslation} from 'react-i18next';
import {StackedModalProps} from '@alchemy/navigation';
import React from 'react';
import RestoreFromTrashIcon from '@mui/icons-material/RestoreFromTrash';
import {Collection} from '../../../types.ts';
import {restoreCollections} from '../../../api/collection.ts';
import {toast} from 'react-toastify';
import CollectionOrStoryChip from '../../Ui/CollectionOrStoryChip.tsx';

type Props = {
    collection: Collection;
    onConfirm?: () => void;
} & StackedModalProps;

export default function CollectionRestoreConfirmDialog({
    collection,
    onConfirm,
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();

    return (
        <ConfirmDialog
            modalIndex={modalIndex}
            title={t(
                'collection.restore.confirm.title',
                'Confirm restore collection'
            )}
            onConfirm={async () => {
                await restoreCollections([collection.id]);
                toast.success(
                    t(
                        'collection_restore.confirmed',
                        'Collection has been restored!'
                    ) as string
                );
                onConfirm?.();
            }}
            open={open}
            confirmButtonProps={{
                startIcon: <RestoreFromTrashIcon />,
            }}
        >
            <Trans
                i18nKey={'collection.restore.confirm_message'}
                values={{collection}}
                defaults={`Are you sure you want to restore collection <coll/> ?`}
                components={{
                    coll: (
                        <CollectionOrStoryChip
                            collection={{
                                ...collection,
                                deleted: false,
                            }}
                        />
                    ),
                }}
            />
        </ConfirmDialog>
    );
}

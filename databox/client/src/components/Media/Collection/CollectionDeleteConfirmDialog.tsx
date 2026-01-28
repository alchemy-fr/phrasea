import {ConfirmDialog} from '@alchemy/phrasea-framework';
import {Trans, useTranslation} from 'react-i18next';
import {StackedModalProps} from '@alchemy/navigation';
import React from 'react';
import {Collection} from '../../../types.ts';
import {deleteCollections} from '../../../api/collection.ts';
import {CollectionChip} from '../../Ui/CollectionChip.tsx';
import {toast} from 'react-toastify';
import DeleteIcon from '@mui/icons-material/Delete';

type Props = {
    collection: Collection;
    onConfirm?: () => void;
} & StackedModalProps;

export default function CollectionDeleteConfirmDialog({
    collection,
    onConfirm,
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();

    return (
        <ConfirmDialog
            modalIndex={modalIndex}
            textToType={collection.title}
            title={
                <Trans
                    i18nKey={'collection.delete.confirm_message'}
                    values={{collection}}
                    defaults={`Are you sure you want to delete collection <coll/> ?`}
                    components={{
                        coll: <CollectionChip collection={collection} />,
                    }}
                />
            }
            onConfirm={async () => {
                await deleteCollections([collection.id]);
                toast.success(
                    t(
                        'collection_delete.confirmed',
                        'Collection has been deleted!'
                    ) as string
                );
                onConfirm?.();
            }}
            open={open}
            confirmButtonProps={{
                startIcon: <DeleteIcon />,
            }}
        />
    );
}

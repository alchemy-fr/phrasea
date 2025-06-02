import ConfirmDialog from '../../../Ui/ConfirmDialog';
import {Trans, useTranslation} from 'react-i18next';
import {
    deleteAssets,
    prepareDeleteAssets,
    PrepareDeleteAssetsOutput,
} from '../../../../api/asset';
import {StackedModalProps} from '@alchemy/navigation';
import {useModalFetch} from '../../../../hooks/useModalFetch.ts';
import FullPageLoader from '../../../Ui/FullPageLoader.tsx';
import React from 'react';
import {Checkbox, FormControlLabel} from '@mui/material';
import AlertDialog from '../../../Dialog/AlertDialog.tsx';
import {CollectionChip} from '../../../Ui/CollectionChip.tsx';

type Props = {
    assetIds: string[];
    onDelete?: () => void;
} & StackedModalProps;

export default function DeleteAssetsConfirm({
    assetIds,
    onDelete,
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const count = assetIds.length;
    const [selection, setSelection] = React.useState<string[]>([]);
    const [hardDelete, setHardDelete] = React.useState(false);

    const {data, isSuccess} = useModalFetch<PrepareDeleteAssetsOutput>({
        queryKey: ['assets', assetIds, 'delete'],
        queryFn: () => prepareDeleteAssets(assetIds),
        staleTime: 2000,
    });

    if (!isSuccess) {
        if (!open) {
            return null;
        }
        return <FullPageLoader />;
    }

    const collections = data.collections;

    const onDeleteAssets = async () => {
        await deleteAssets(assetIds, {
            collections: !hardDelete ? selection : [],
        });
        onDelete && onDelete();
    };

    if (collections.length === 0 && !data.canDelete) {
        return (
            <AlertDialog modalIndex={modalIndex} open={open}>
                {t(
                    'asset.delete.confirm.not_allowed',
                    `You don't have permission to delete any of these assets`
                )}
            </AlertDialog>
        );
    }

    return (
        <ConfirmDialog
            modalIndex={modalIndex}
            title={t('asset.delete.confirm.title', 'Confirm delete')}
            onConfirm={onDeleteAssets}
            open={open}
        >
            {collections.length > 0 ? (
                <>
                    <FormControlLabel
                        sx={{
                            my: 1,
                        }}
                        checked={hardDelete}
                        disabled={!data.canDelete}
                        onChange={(_e, checked) => setHardDelete(checked)}
                        label={
                            <Trans
                                i18nKey="asset.delete.hard_delete"
                                defaults={`Delete asset permanently`}
                            />
                        }
                        control={<Checkbox color={'error'} sx={{mr: 1}} />}
                    />
                    {collections.map(collection => (
                        <div key={collection.id}>
                            <FormControlLabel
                                sx={{
                                    my: 1,
                                }}
                                disabled={hardDelete}
                                checked={
                                    !hardDelete &&
                                    selection.includes(collection.id)
                                }
                                onChange={(_e, checked) => {
                                    if (checked) {
                                        setSelection(p =>
                                            p.concat([collection.id])
                                        );
                                    } else {
                                        setSelection(p =>
                                            p.filter(id => id !== collection.id)
                                        );
                                    }
                                }}
                                label={
                                    <Trans
                                        i18nKey="asset.delete.remove_from_collection"
                                        values={{
                                            name: collection.absoluteTitleTranslated,
                                        }}
                                        defaults={`Remove from collection <strong>{{name}}</strong>`}
                                        components={{
                                            strong: <CollectionChip />,
                                        }}
                                    />
                                }
                                control={<Checkbox sx={{mr: 1}} />}
                            />
                        </div>
                    ))}
                </>
            ) : (
                <>
                    {t(
                        'asset.delete.confirm_message',
                        'Are you sure you want to delete {{count}} assets?',
                        {
                            count,
                        }
                    )}
                </>
            )}
        </ConfirmDialog>
    );
}

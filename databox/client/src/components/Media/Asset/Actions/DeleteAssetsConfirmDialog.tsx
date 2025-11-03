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
import {Alert, Box, Checkbox, FormControlLabel} from '@mui/material';
import AlertDialog from '../../../Dialog/AlertDialog.tsx';
import {CollectionChip} from '../../../Ui/CollectionChip.tsx';
import CollectionStoryChip from '../../../Ui/CollectionStoryChip.tsx';
import DeleteIcon from '@mui/icons-material/Delete';

type Props = {
    assetIds: string[];
    onDelete?: () => void;
} & StackedModalProps;

export default function DeleteAssetsConfirmDialog({
    assetIds,
    onDelete,
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const count = assetIds.length;
    const [selection, setSelection] = React.useState<string[]>([]);
    const [moveToTrash, setMoveToTrash] = React.useState(false);

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
            collections: !moveToTrash ? selection : [],
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

    const disabled =
        collections.length > 0 && selection.length === 0 && !moveToTrash;

    return (
        <ConfirmDialog
            modalIndex={modalIndex}
            title={t('asset.delete.confirm.title', 'Confirm delete')}
            onConfirm={onDeleteAssets}
            disabled={disabled}
            open={open}
            confirmButtonProps={{
                startIcon: <DeleteIcon />,
            }}
        >
            {collections.length > 0 ? (
                <>
                    <FormControlLabel
                        sx={{
                            my: 1,
                        }}
                        checked={moveToTrash}
                        disabled={!data.canDelete}
                        onChange={(_e, checked) => setMoveToTrash(checked)}
                        label={
                            <Trans
                                i18nKey="asset.delete.move_to_trash"
                                defaults={`Move asset to trash`}
                                tOptions={{
                                    defaultValue_other: `Move <strong>{{count}} assets</strong> to trash`,
                                }}
                                count={count}
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
                                disabled={moveToTrash}
                                checked={
                                    !moveToTrash &&
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
                                    collection.storyAsset ? (
                                        <Trans
                                            i18nKey="asset.delete.remove_from_story"
                                            values={{
                                                name:
                                                    collection.storyAsset
                                                        .resolvedTitle ||
                                                    collection.storyAsset.title,
                                            }}
                                            defaults={`Remove from story <strong>{{name}}</strong>`}
                                            components={{
                                                strong: (
                                                    <CollectionStoryChip
                                                        storyAsset={
                                                            collection.storyAsset
                                                        }
                                                    />
                                                ),
                                            }}
                                        />
                                    ) : (
                                        <Trans
                                            i18nKey="asset.delete.remove_from_collection"
                                            values={{
                                                name: collection.absoluteTitleTranslated,
                                            }}
                                            defaults={`Remove from collection <strong>{{name}}</strong>`}
                                            components={{
                                                strong: (
                                                    <CollectionChip
                                                        collection={collection}
                                                    />
                                                ),
                                            }}
                                        />
                                    )
                                }
                                control={<Checkbox sx={{mr: 1}} />}
                            />
                        </div>
                    ))}
                </>
            ) : (
                <>
                    <Trans
                        i18nKey="asset.delete.confirm_move_to_trash_message"
                        defaults={`Are you sure you want to move this asset to trash?`}
                        tOptions={{
                            defaultValue_other: `Are you sure you want to move <strong>{{count}} assets</strong> to trash?`,
                        }}
                        count={count}
                    />
                </>
            )}
            {data.shareCount > 0 ? (
                <Box>
                    <Alert severity="warning" sx={{mt: 2}}>
                        <Trans
                            i18nKey={'asset.delete.shared_warning'}
                            defaults={`This asset is currently shared. Deleting it will remove access for all users.`}
                            tOptions={{
                                defaultValue_other: `<strong>{{count}}</strong> of these assets are currently shared. Deleting them will remove access for all users.`,
                            }}
                            count={data.shareCount}
                        />
                    </Alert>
                </Box>
            ) : null}
        </ConfirmDialog>
    );
}

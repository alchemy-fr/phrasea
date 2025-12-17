import {ConfirmDialog} from '@alchemy/phrasea-framework';
import {useTranslation} from 'react-i18next';
import {restoreAssets} from '../../../../api/asset';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import React, {MouseEvent} from 'react';
import {Asset, Collection} from '../../../../types.ts';
import {
    Alert,
    Box,
    List,
    ListItem,
    ListItemSecondaryAction,
    ListItemText,
} from '@mui/material';
import Button from '@mui/material/Button';
import RestoreFromTrashIcon from '@mui/icons-material/RestoreFromTrash';
import CollectionRestoreConfirmDialog from '../../Collection/CollectionRestoreConfirmDialog.tsx';
import CollectionOrStoryChip from '../../../Ui/CollectionOrStoryChip.tsx';

type Props = {
    assets: Asset[];
    onRestore?: () => void;
} & StackedModalProps;

export default function RestoreAssetsConfirm({
    assets,
    onRestore,
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const restorableAssets = assets.filter(
        a =>
            !a.referenceCollection?.deleted ||
            assets.some(sa => sa.id === a.referenceCollection?.storyAsset?.id)
    );
    const count = restorableAssets.length;

    const onRestoreAssets = async () => {
        await restoreAssets(restorableAssets.map(a => a.id));
        onRestore && onRestore();
    };

    const collections: Collection[] = (() => {
        const index = new Map<string, Collection>();
        assets.forEach(a => {
            if (
                a.referenceCollection?.deleted &&
                a.referenceCollection.id &&
                !assets.some(
                    sa => sa.id === a.referenceCollection?.storyAsset?.id
                )
            ) {
                index.set(a.referenceCollection.id, a.referenceCollection);
            }
        });

        return Array.from(index.values());
    })();

    const onRestoreCollection =
        (collection: Collection) =>
        (e: MouseEvent): void => {
            e.stopPropagation();

            openModal(CollectionRestoreConfirmDialog, {
                collection,
            });
        };

    return (
        <ConfirmDialog
            modalIndex={modalIndex}
            title={t('asset.restore.confirm.title', 'Confirm restore')}
            onConfirm={onRestoreAssets}
            open={open}
            disabled={count === 0}
            confirmButtonProps={{
                startIcon: <RestoreFromTrashIcon />,
            }}
        >
            {count > 0
                ? t('asset.restore.confirm_message', {
                      defaultValue:
                          'Are you sure you want to restore this asset?',
                      defaultValue_other:
                          'Are you sure you want to restore {{count}} assets?',
                      count,
                  })
                : t(
                      'asset.restore.no_restorable_assets',
                      'None of the selected assets can be restored because their reference collections have been deleted.'
                  )}

            {collections.length > 0 ? (
                <Box sx={{mt: 2}}>
                    <Alert severity={'warning'}>
                        {t(
                            'asset.restore.deleted_collection_or_story_warning',
                            'Note: Some assets cannot be restored because their reference collections or story have been deleted:'
                        )}
                    </Alert>
                    <List>
                        {collections.map(c => (
                            <ListItem key={c!.id}>
                                <ListItemText
                                    primary={
                                        <CollectionOrStoryChip collection={c} />
                                    }
                                />
                                <ListItemSecondaryAction>
                                    <Button
                                        onClick={onRestoreCollection(c)}
                                        startIcon={<RestoreFromTrashIcon />}
                                    >
                                        {t(
                                            'asset.restore.deleted_collection',
                                            'Restore'
                                        )}
                                    </Button>
                                </ListItemSecondaryAction>
                            </ListItem>
                        ))}
                    </List>
                </Box>
            ) : null}
        </ConfirmDialog>
    );
}

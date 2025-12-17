import {Asset} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {
    Alert,
    Button,
    List,
    ListItem,
    ListItemIcon,
    ListItemSecondaryAction,
    Typography,
} from '@mui/material';
import {
    deleteAssets,
    deleteAssetShortcut,
    restoreAssets,
} from '../../../api/asset';
import {Trans, useTranslation} from 'react-i18next';
import {FormSection} from '@alchemy/react-form';
import {ConfirmDialog} from '@alchemy/phrasea-framework';
import {useModals} from '@alchemy/navigation';
import ShortcutIcon from '@mui/icons-material/Shortcut';
import {WorkspaceChip} from '../../Ui/WorkspaceChip.tsx';
import {CollectionChip} from '../../Ui/CollectionChip.tsx';
import {useAssetStore} from '../../../store/assetStore.ts';

type Props = {
    data: Asset;
} & DialogTabProps;

export default function OperationsAsset({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const loadAsset = useAssetStore(s => s.loadAsset);

    const deleteConfirmAsset = async () => {
        openModal(ConfirmDialog, {
            textToType: data.title,
            title: t(
                'asset_delete.confirm',
                'Are you sure you want to delete this asset?'
            ),
            onConfirm: async () => {
                await deleteAssets([data.id]);
            },
            onConfirmed: () => {
                onClose();
            },
        });
    };
    const restoreConfirmAsset = async () => {
        openModal(ConfirmDialog, {
            title: t(
                'asset_restore.confirm',
                'Are you sure you want to restore this asset?'
            ),
            onConfirm: async () => {
                await restoreAssets([data.id]);
            },
            onConfirmed: () => {
                onClose();
            },
        });
    };

    const otherCollections =
        data.collections?.filter(
            c => c.id !== data.referenceCollection?.id && !c.storyAsset
        ) ?? [];

    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <div>
                <Typography variant={'h2'} sx={{mb: 1}}>
                    {t('asset_collections.title', 'Collections')}
                </Typography>

                <Typography variant={'body1'} sx={{mb: 1}}>
                    {data.referenceCollection ? (
                        <Trans
                            i18nKey={'asset_collections.reference_collection'}
                            values={{
                                collection:
                                    data.referenceCollection
                                        .absoluteTitleTranslated,
                                workspace: data.workspace.nameTranslated,
                            }}
                            components={{
                                strong: (
                                    <CollectionChip
                                        collection={data.referenceCollection}
                                    />
                                ),
                                i: <WorkspaceChip workspace={data.workspace} />,
                            }}
                            defaults={
                                'This asset belongs to the collection <strong>{{collection}}</strong> in the workspace <i>{{workspace}}</i>.'
                            }
                        />
                    ) : (
                        <Trans
                            i18nKey={'asset_collections.reference_workspace'}
                            values={{
                                workspace: data.workspace.nameTranslated,
                            }}
                            components={{
                                strong: (
                                    <WorkspaceChip workspace={data.workspace} />
                                ),
                            }}
                            defaults={
                                'This asset belongs to the workspace <strong>{{workspace}}</strong> root collection.'
                            }
                        />
                    )}
                </Typography>

                {otherCollections.length > 0 ? (
                    <>
                        <Typography variant={'body2'} sx={{mb: 1}}>
                            {t(
                                'asset_collections.other_collections',
                                'It also appears as shortcut in the following collections:'
                            )}
                        </Typography>

                        <List
                            sx={{
                                mb: 2,
                            }}
                        >
                            {otherCollections.map(c => {
                                return (
                                    <ListItem key={c.id}>
                                        <ListItemIcon>
                                            <ShortcutIcon />
                                        </ListItemIcon>
                                        <CollectionChip
                                            collection={c}
                                            label={c.absoluteTitleTranslated}
                                        />

                                        <ListItemSecondaryAction>
                                            <Button
                                                color={'error'}
                                                onClick={() => {
                                                    openModal(ConfirmDialog, {
                                                        title: t(
                                                            'asset_collections.remove_shortcut_confirm',
                                                            'Are you sure you want to remove this shortcut?'
                                                        ),
                                                        onConfirm: async () => {
                                                            await deleteAssetShortcut(
                                                                data.id,
                                                                c.id
                                                            );
                                                            loadAsset(data.id);
                                                        },
                                                    });
                                                }}
                                            >
                                                {t(
                                                    'asset_collections.remove_shortcut',
                                                    'Remove shortcut'
                                                )}
                                            </Button>
                                        </ListItemSecondaryAction>
                                    </ListItem>
                                );
                            })}
                        </List>
                    </>
                ) : (
                    ''
                )}
            </div>
            <FormSection>
                <Alert
                    color={'error'}
                    sx={{
                        mb: 2,
                    }}
                >
                    {t('danger_zone', 'Danger zone')}
                </Alert>
                <Typography variant={'h2'} sx={{mb: 1}}>
                    {data.deleted
                        ? t('asset_restore.title', 'Restore Asset')
                        : t('asset_delete.title', 'Delete Asset')}
                </Typography>
                <Button
                    onClick={
                        !data.deleted ? deleteConfirmAsset : restoreConfirmAsset
                    }
                    color={'error'}
                >
                    {data.deleted
                        ? t('asset_restore.title', 'Restore Asset')
                        : t('asset_delete.title', 'Delete Asset')}
                </Button>
            </FormSection>
        </ContentTab>
    );
}

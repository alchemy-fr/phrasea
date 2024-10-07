import {Asset, StateSetter} from '../../../types';
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
import {deleteAsset, deleteAssetShortcut, getAsset} from '../../../api/asset';
import {Trans, useTranslation} from 'react-i18next';
import {FormSection} from '../../../../../../lib/js/react-form';
import ConfirmDialog from '../../Ui/ConfirmDialog.tsx';
import {useModals} from '../../../../../../lib/js/navigation';
import ShortcutIcon from '@mui/icons-material/Shortcut';
import {CollectionChip, WorkspaceChip} from '../../Ui/Chips.tsx';

type Props = {
    data: Asset;
    setData: StateSetter<Asset>;
} & DialogTabProps;

export default function OperationsAsset({
    data,
    onClose,
    minHeight,
    setData,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();

    const deleteConfirmAsset = async () => {
        openModal(ConfirmDialog, {
            textToType: data.title,
            title: t(
                'asset_delete.confirm',
                'Are you sure you want to delete this asset?'
            ),
            onConfirm: async () => {
                await deleteAsset(data.id);
            },
            onConfirmed: () => {
                onClose();
            },
        });
    };

    const otherCollections =
        data.collections?.filter(c => c.id !== data.referenceCollection?.id) ??
        [];

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
                                    data.referenceCollection.absoluteTitle,
                            }}
                            components={{
                                strong: <CollectionChip />,
                                i: <WorkspaceChip />,
                            }}
                            defaults={
                                'This asset belongs to the collection <strong>{{collection}}</strong> in the workspace root <i>{{workspace}}</i>.'
                            }
                        />
                    ) : (
                        <Trans
                            i18nKey={'asset_collections.reference_workspace'}
                            values={{
                                workspace: data.workspace.name,
                            }}
                            components={{
                                strong: <WorkspaceChip />,
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
                                            label={c.absoluteTitle}
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
                                                            setData(
                                                                await getAsset(
                                                                    data.id
                                                                )
                                                            );
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
                    {t('asset_delete.title', 'Delete Asset')}
                </Typography>
                <Button onClick={deleteConfirmAsset} color={'error'}>
                    {t('asset_delete.title', 'Delete Asset')}
                </Button>
            </FormSection>
        </ContentTab>
    );
}

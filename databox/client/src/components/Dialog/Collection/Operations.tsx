import {Collection} from '../../../types';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import CollectionMoveSection from '../../Media/Collection/CollectionMoveSection';
import {FormSection} from '@alchemy/react-form';
import {Alert, Button, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {useModals} from '@alchemy/navigation';
import CollectionRestoreConfirmDialog from '../../Media/Collection/CollectionRestoreConfirmDialog.tsx';
import CollectionDeleteConfirmDialog from '../../Media/Collection/CollectionDeleteConfirmDialog.tsx';

type Props = DataTabProps<Collection>;

export default function Operations({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();

    const deleteConfirmCollection = async () => {
        openModal(CollectionDeleteConfirmDialog, {
            collection: data,
            onConfirm: () => {
                onClose();
            },
        });
    };

    const restoreConfirmCollection = async () => {
        openModal(CollectionRestoreConfirmDialog, {
            collection: data,
            onConfirm: () => {
                onClose();
            },
        });
    };

    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            {data.capabilities.canEdit && (
                <CollectionMoveSection
                    collection={data}
                    onMoved={() => {
                        onClose();
                    }}
                />
            )}
            {data.capabilities.canDelete && (
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
                            ? t(
                                  'collection_restore.title',
                                  'Restore collection'
                              )
                            : t('collection_delete.title', 'Delete collection')}
                    </Typography>
                    <Button
                        onClick={
                            data.deleted
                                ? restoreConfirmCollection
                                : deleteConfirmCollection
                        }
                        color={'error'}
                    >
                        {data.deleted
                            ? t(
                                  'collection_restore.title',
                                  'Restore collection'
                              )
                            : t('collection_delete.title', 'Delete collection')}
                    </Button>
                </FormSection>
            )}
        </ContentTab>
    );
}

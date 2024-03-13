import {Collection} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import CollectionMoveSection from '../../Media/Collection/CollectionMoveSection';
import {FormSection} from '@alchemy/react-form';
import {Alert, Button, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {deleteCollection} from '../../../api/collection';
import ConfirmDialog from '../../Ui/ConfirmDialog';
import {useModals} from '@alchemy/navigation';

type Props = {
    data: Collection;
} & DialogTabProps;

export default function Operations({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const deleteConfirmCollection = async () => {
        openModal(ConfirmDialog, {
            textToType: data.title,
            title: t(
                'collection_delete.title.confirm',
                'Are you sure you want to delete this collection?'
            ),
            onConfirm: async () => {
                await deleteCollection(data.id);
            },
            onConfirmed: () => {
                onClose();
            },
        });
    };
    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <CollectionMoveSection
                collection={data}
                onMoved={() => {
                    onClose();
                }}
            />
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
                    {t('collection_delete.title', 'Delete collection')}
                </Typography>
                <Button onClick={deleteConfirmCollection} color={'error'}>
                    {t('collection_delete.title', 'Delete collection')}
                </Button>
            </FormSection>
        </ContentTab>
    );
}

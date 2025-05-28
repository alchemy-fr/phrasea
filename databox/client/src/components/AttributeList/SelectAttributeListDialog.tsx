import {Button, ListItem, Skeleton} from '@mui/material';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useAttributeListStore} from '../../store/attributeListStore';
import {AppDialog} from '@alchemy/phrasea-ui';
import {AttributeList} from '../../types';
import {useTranslation} from 'react-i18next';
import AttributeListMenuItem from './AttributeListMenuItem';
import CreateAttributeList from './CreateAttributeList';
import AddIcon from '@mui/icons-material/Add';
import {useEffect} from 'react';
import {useNavigateToModal} from '../Routing/ModalLink.tsx';
import {modalRoutes} from '../../routes.ts';

type Props = {} & StackedModalProps;

export default function SelectAttributeListDialog({modalIndex, open}: Props) {
    const {t} = useTranslation();
    const {openModal, closeModal} = useModals();
    const navigateToModal = useNavigateToModal();

    const current = useAttributeListStore(state => state.current);
    const setCurrent = useAttributeListStore(state => state.setCurrent);
    const deleteAttributeList = useAttributeListStore(
        state => state.deleteAttributeList
    );
    const load = useAttributeListStore(state => state.load);
    const loading = useAttributeListStore(state => !state.loaded);
    const lists = useAttributeListStore(state => state.lists);

    useEffect(() => {
        load();
    }, [load]);

    const onSelect = (data: AttributeList): void => {
        setCurrent(data.id);
        closeModal();
    };

    const onEdit = (id: string): void => {
        closeModal();
        navigateToModal(modalRoutes.attributeList.routes.manage, {
            id,
            tab: 'organize',
        });
    };

    const createAttributeList = () => {
        openModal(CreateAttributeList, {
            onCreate: data => {
                onSelect(data);
            },
        });
    };

    return (
        <AppDialog
            maxWidth={'sm'}
            modalIndex={modalIndex}
            open={open}
            loading={loading}
            onClose={closeModal}
            title={t(
                'attributeList.choose_modal.title',
                'Select current Attribute List'
            )}
            actions={({onClose}) => (
                <>
                    <Button
                        variant={'contained'}
                        onClick={createAttributeList}
                        startIcon={<AddIcon />}
                    >
                        {t(
                            'attributeList.create_button.label',
                            'Create new Attribute List'
                        )}
                    </Button>
                    <Button
                        onClick={onClose}
                        color={'warning'}
                        disabled={loading}
                    >
                        {t('dialog.cancel', 'Cancel')}
                    </Button>
                </>
            )}
        >
            {!loading ? (
                lists.map(al => (
                    <AttributeListMenuItem
                        key={al.id}
                        onClick={() => onSelect(al)}
                        data={al}
                        selected={al.id === current?.id}
                        onDelete={deleteAttributeList}
                        onEdit={onEdit}
                    />
                ))
            ) : (
                <>
                    <ListItem>
                        <Skeleton variant={'text'} width={'100%'} />
                    </ListItem>
                    <ListItem>
                        <Skeleton variant={'text'} width={'100%'} />
                    </ListItem>
                </>
            )}
        </AppDialog>
    );
}

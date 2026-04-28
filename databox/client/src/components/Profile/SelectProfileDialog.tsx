import {Button, ListItem, Skeleton} from '@mui/material';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useProfileStore} from '../../store/profileStore.ts';
import {AppDialog} from '@alchemy/phrasea-ui';
import {Profile} from '../../types';
import {useTranslation} from 'react-i18next';
import ProfileMenuItem from './ProfileMenuItem.tsx';
import CreateProfileDialog from './CreateProfileDialog.tsx';
import AddIcon from '@mui/icons-material/Add';
import {useEffect, useState} from 'react';
import {useNavigateToModal} from '../Routing/ModalLink.tsx';
import {modalRoutes} from '../../routes.ts';
import {useAuth} from '@alchemy/react-auth';

type Props = {} & StackedModalProps;

export default function SelectProfileDialog({modalIndex, open}: Props) {
    const {t} = useTranslation();
    const {isAuthenticated} = useAuth();
    const {openModal, closeModal} = useModals();
    const navigateToModal = useNavigateToModal();

    const current = useProfileStore(state => state.current);
    const syncData = useProfileStore(state => state.syncData);
    const arePreferencesSynced = useProfileStore(
        state => state.arePreferencesSynced
    );
    const setCurrent = useProfileStore(state => state.setCurrent);
    const deleteProfile = useProfileStore(state => state.deleteProfile);
    const load = useProfileStore(state => state.load);
    const loading = useProfileStore(state => !state.loaded);
    const lists = useProfileStore(state => state.profiles);
    const [synced, setSynced] = useState<boolean | undefined>();

    useEffect(() => {
        load();
    }, [load]);

    const onSelect = (data: Profile): void => {
        setCurrent(data.id);
        closeModal();
    };

    useEffect(() => {
        if (current) {
            arePreferencesSynced(current).then(setSynced);
        }
    }, [arePreferencesSynced, current]);

    const onEdit = (id: string): void => {
        closeModal();
        navigateToModal(modalRoutes.profiles.routes.manage, {
            id,
            tab: 'organize',
        });
    };

    const createProfile = () => {
        openModal(CreateProfileDialog, {
            onCreate: data => {
                onSelect(data);
                onEdit(data.id);
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
            title={t('profile.choose_modal.title', 'Select current Profile')}
            actions={({onClose}) => (
                <>
                    <Button
                        variant={'contained'}
                        onClick={createProfile}
                        startIcon={<AddIcon />}
                        disabled={!isAuthenticated}
                    >
                        {t('profile.create_button.label', 'Create new Profile')}
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
                lists.map(p => (
                    <ProfileMenuItem
                        key={p.id}
                        onClick={() => onSelect(p)}
                        data={p}
                        selected={p.id === current?.id}
                        onDelete={deleteProfile}
                        onEdit={onEdit}
                        syncData={
                            current?.id === p.id && !synced
                                ? syncData
                                : undefined
                        }
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

import {useEffect} from 'react';
import TabbedDialog from '../Tabbed/TabbedDialog';
import {useTranslation} from 'react-i18next';
import {useParams} from '@alchemy/navigation';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import Acl from './Acl';
import {modalRoutes} from '../../../routes';
import {useCloseModal} from '../../Routing/ModalLink';
import EditProfile from './EditProfile.tsx';
import OrganizeProfile from './OrganizeProfile.tsx';
import {useProfileStore} from '../../../store/profileStore.ts';

type Props = {};

export default function ProfileDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const closeModal = useCloseModal();

    const loadProfile = useProfileStore(state => state.loadProfile);
    const profiles = useProfileStore(state => state.profiles);
    const data = profiles.find(p => p.id === id);

    useEffect(() => {
        loadProfile(id!).catch(() => {
            closeModal();
        });
    }, [loadProfile, id]);

    if (!data) {
        return <FullPageLoader />;
    }

    return (
        <TabbedDialog
            route={modalRoutes.profiles.routes.manage}
            routeParams={{id}}
            maxWidth={'md'}
            title={t('profile.manage.title', 'Manage Profile {{name}}', {
                name: data.title,
            })}
            tabs={[
                {
                    title: t('profile.manage.organize.title', 'Organize'),
                    component: OrganizeProfile,
                    id: 'organize',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('profile.manage.edit.title', 'Edit'),
                    component: EditProfile,
                    id: 'edit',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('profile.manage.acl.title', 'Permissions'),
                    component: Acl,
                    id: 'permissions',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEditPermissions,
                },
            ]}
        />
    );
}

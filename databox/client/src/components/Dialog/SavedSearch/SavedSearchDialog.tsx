import {useEffect, useState} from 'react';
import TabbedDialog from '../Tabbed/TabbedDialog';
import {useTranslation} from 'react-i18next';
import {useParams} from '@alchemy/navigation';
import FullPageLoader from '../../Ui/FullPageLoader';
import {SavedSearch} from '../../../types';
import Acl from './Acl';
import InfoSavedSearch from './InfoSavedSearch';
import {modalRoutes} from '../../../routes';
import {useCloseModal} from '../../Routing/ModalLink';
import {getSavedSearch} from '../../../api/savedSearch.ts';
import EditSavedSearch from './EditSavedSearch';
import Automations from './Automations.tsx';

type Props = {};

export default function SavedSearchDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const [data, setData] = useState<SavedSearch>();
    const closeModal = useCloseModal();

    useEffect(() => {
        getSavedSearch(id!)
            .then(c => setData(c))
            .catch(() => {
                closeModal();
            });
    }, [id]);

    if (!data) {
        return <FullPageLoader />;
    }

    return (
        <TabbedDialog
            route={modalRoutes.savedSearch.routes.manage}
            routeParams={{id}}
            maxWidth={'md'}
            title={t(
                'saved_search.manage.title',
                'Manage Saved Search {{name}}',
                {
                    name: data.title,
                }
            )}
            tabs={[
                {
                    title: t('saved_search.manage.info.title', 'Info'),
                    component: InfoSavedSearch,
                    id: 'info',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('saved_search.manage.edit.title', 'Edit'),
                    component: EditSavedSearch,
                    id: 'edit',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('saved_search.manage.acl.title', 'Permissions'),
                    component: Acl,
                    id: 'permissions',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEditPermissions,
                },
                {
                    title: t(
                        'saved_search.manage.automations.title',
                        'Automations'
                    ),
                    component: Automations,
                    id: 'automations',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
            ]}
        />
    );
}

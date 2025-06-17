import {useEffect, useState} from 'react';
import TabbedDialog from '../Tabbed/TabbedDialog';
import {useTranslation} from 'react-i18next';
import EditCollection from './EditCollection';
import {useParams} from '@alchemy/navigation';
import FullPageLoader from '../../Ui/FullPageLoader';
import {Collection} from '../../../types';
import Acl from './Acl';
import {getCollection} from '../../../api/collection';
import TagRulesTab from './TagRulesTab';
import Operations from './Operations';
import InfoCollection from './InfoCollection';
import {modalRoutes} from '../../../routes';
import {useCloseModal} from '../../Routing/ModalLink';
import ESDocument from '../Asset/ESDocument.tsx';
import {useAuth} from '@alchemy/react-auth';
import CollectionNotifications from './CollectionNotifications.tsx';

type Props = {};

export default function CollectionDialog({}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const [data, setData] = useState<Collection>();
    const closeModal = useCloseModal();
    const {user} = useAuth();

    useEffect(() => {
        getCollection(id!)
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
            route={modalRoutes.collections.routes.manage}
            routeParams={{id}}
            maxWidth={'md'}
            title={t('collection.manage.title', 'Manage collection {{name}}', {
                name: data.titleTranslated,
            })}
            tabs={[
                {
                    title: t('collection.manage.info.title', 'Info'),
                    component: InfoCollection,
                    id: 'info',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t('collection.manage.edit.title', 'Edit'),
                    component: EditCollection,
                    id: 'edit',
                    props: {
                        data,
                        setData,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t(
                        'collection.manage.notifications.title',
                        'Notifications'
                    ),
                    component: CollectionNotifications,
                    id: 'notifications',
                    props: {
                        data,
                    },
                },
                {
                    title: t('collection.manage.acl.title', 'Permissions'),
                    component: Acl,
                    id: 'permissions',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEditPermissions,
                },
                {
                    title: t('collection.manage.tag_rules.title', 'Tag rules'),
                    component: TagRulesTab,
                    id: 'tag-rules',
                    props: {
                        data,
                    },
                    enabled: data.capabilities.canEdit,
                },
                {
                    title: t(
                        'collection.manage.operations.title',
                        'Operations'
                    ),
                    component: Operations,
                    id: 'ops',
                    props: {
                        data,
                    },
                    enabled:
                        data.capabilities.canEdit ||
                        data.capabilities.canDelete,
                },
                {
                    title: t('collection.manage.es_doc.title', 'ES Document'),
                    component: ESDocument,
                    id: 'es_doc',
                    props: {
                        data,
                        entity: 'collections',
                    },
                    enabled: user?.roles?.includes('tech') ?? false,
                },
            ]}
        />
    );
}

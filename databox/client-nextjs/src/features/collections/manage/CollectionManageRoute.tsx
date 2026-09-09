'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    BellIcon,
    DatabaseIcon,
    InfoIcon,
    PencilIcon,
    ShieldIcon,
    WrenchIcon,
} from 'lucide-react';
import type {Collection} from '@/types/api';
import {getCollection} from '@/lib/api/collections';
import {
    TabbedRouteDialog,
    DialogTab,
} from '@/components/modals/TabbedRouteDialog';
import {RouteDialog} from '@/components/modals/RouteDialog';
import {FullPageLoader} from '@/components/ui/loader';
import {routes} from '@/lib/routes';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {useCollectionStore} from '../collectionStore';
import {CollectionInfoTab} from './tabs/CollectionInfoTab';
import {CollectionEditTab} from './tabs/CollectionEditTab';
import {CollectionNotificationsTab} from './tabs/CollectionNotificationsTab';
import {CollectionPermissionsTab} from './tabs/CollectionPermissionsTab';
import {CollectionOperationsTab} from './tabs/CollectionOperationsTab';
import {ESDocumentTab} from '@/features/assets/manage/tabs/ESDocumentTab';
import {EntityName} from '@/types/api';

export type CollectionTabProps = {collection: Collection; refresh: () => void};

export function CollectionManageRoute({
    collectionId,
    tab,
}: {
    collectionId: string;
    tab: string;
}) {
    const {t} = useTranslation();
    const {hasRole} = useAuth();
    const upsert = useCollectionStore(s => s.upsertCollection);
    const query = useQuery({
        queryKey: ['collection', collectionId],
        queryFn: async () => {
            const c = await getCollection(collectionId);
            upsert(c);

            return c;
        },
    });
    const collection = query.data;

    if (!collection) {
        return (
            <RouteDialog size="lg">
                <FullPageLoader />
            </RouteDialog>
        );
    }

    const tabs: DialogTab<CollectionTabProps>[] = [
        {
            id: 'info',
            title: t('collection.manage.info', 'Info'),
            icon: <InfoIcon />,
            component: CollectionInfoTab,
        },
        {
            id: 'edit',
            title: t('common.edit', 'Edit'),
            icon: <PencilIcon />,
            component: CollectionEditTab,
            enabled: collection.capabilities.edit,
        },
        {
            id: 'notifications',
            title: t('collection.manage.notifications', 'Notifications'),
            icon: <BellIcon />,
            component: CollectionNotificationsTab,
        },
        {
            id: 'permissions',
            title: t('collection.manage.permissions', 'Permissions'),
            icon: <ShieldIcon />,
            component: CollectionPermissionsTab,
            enabled: collection.capabilities.editPermissions,
        },
        {
            id: 'operations',
            title: t('collection.manage.operations', 'Operations'),
            icon: <WrenchIcon />,
            component: CollectionOperationsTab,
            enabled:
                collection.capabilities.edit || collection.capabilities.delete,
        },
        {
            id: 'es',
            title: t('asset.manage.es_document', 'ES Document'),
            icon: <DatabaseIcon />,
            component: ({collection: c}) => (
                <ESDocumentTab entity={EntityName.Collection} id={c.id} />
            ),
            enabled: hasRole(AppRole.Tech),
        },
    ];

    return (
        <TabbedRouteDialog<CollectionTabProps>
            title={t('collection.manage.title', 'Manage collection')}
            subtitle={
                collection.absoluteDisplayName ??
                collection.displayName ??
                collection.name
            }
            tabs={tabs}
            activeTab={tab}
            buildTabHref={next => routes.collectionManage(collectionId, next)}
            baseProps={{collection, refresh: () => query.refetch()}}
        />
    );
}

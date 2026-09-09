'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    FilterIcon,
    ImagesIcon,
    InfoIcon,
    ListIcon,
    PencilIcon,
    PlugIcon,
    ScaleIcon,
    ShieldIcon,
    SlidersHorizontalIcon,
    TagIcon,
    ShieldCheckIcon,
    FileLockIcon,
} from 'lucide-react';
import type {Workspace} from '@/types/api';
import {getWorkspace} from '@/lib/api/collections';
import {
    TabbedRouteDialog,
    DialogTab,
} from '@/components/modals/TabbedRouteDialog';
import {RouteDialog} from '@/components/modals/RouteDialog';
import {FullPageLoader} from '@/components/ui/loader';
import {routes} from '@/lib/routes';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {WorkspaceInfoTab} from './tabs/WorkspaceInfoTab';
import {WorkspaceEditTab} from './tabs/WorkspaceEditTab';
import {WorkspacePermissionsTab} from './tabs/WorkspacePermissionsTab';
import {TagManagerTab} from './tabs/TagManagerTab';
import {EntityListsTab} from './tabs/EntityListsTab';
import {AttributeDefinitionsTab} from './tabs/AttributeDefinitionsTab';
import {AttributePoliciesTab} from './tabs/AttributePoliciesTab';
import {RenditionDefinitionsTab} from './tabs/RenditionDefinitionsTab';
import {RenditionPoliciesTab} from './tabs/RenditionPoliciesTab';
import {AssetPoliciesTab} from './tabs/AssetPoliciesTab';
import {IntegrationsTab} from './tabs/IntegrationsTab';
import {FilterRulesTab} from './tabs/FilterRulesTab';

export type WorkspaceTabProps = {workspace: Workspace; refresh: () => void};

export function WorkspaceManageRoute({
    workspaceId,
    tab,
}: {
    workspaceId: string;
    tab: string;
}) {
    const {t} = useTranslation();
    const upsertWorkspace = useCollectionStore(s => s.upsertWorkspace);
    const query = useQuery({
        queryKey: ['workspace', workspaceId],
        queryFn: async () => {
            const ws = await getWorkspace(workspaceId);
            upsertWorkspace(ws);

            return ws;
        },
    });
    const workspace = query.data;
    if (!workspace) {
        return (
            <RouteDialog size="xl">
                <FullPageLoader />
            </RouteDialog>
        );
    }
    const canEdit = workspace.capabilities.edit;

    const tabs: DialogTab<WorkspaceTabProps>[] = [
        {
            id: 'info',
            title: t('collection.manage.info', 'Info'),
            icon: <InfoIcon />,
            component: WorkspaceInfoTab,
        },
        {
            id: 'edit',
            title: t('common.edit', 'Edit'),
            icon: <PencilIcon />,
            component: WorkspaceEditTab,
            enabled: canEdit,
        },
        {
            id: 'permissions',
            title: t('collection.manage.permissions', 'Permissions'),
            icon: <ShieldIcon />,
            component: WorkspacePermissionsTab,
            enabled: workspace.capabilities.editPermissions,
        },
        {
            id: 'tags',
            title: t('workspace.manage.tags', 'Tags'),
            icon: <TagIcon />,
            component: TagManagerTab,
            enabled: canEdit,
        },
        {
            id: 'entities',
            title: t('workspace.manage.entities', 'Entities'),
            icon: <ListIcon />,
            component: EntityListsTab,
            enabled: canEdit,
        },
        {
            id: 'attributes',
            title: t('workspace.manage.attributes', 'Attributes'),
            icon: <SlidersHorizontalIcon />,
            component: AttributeDefinitionsTab,
            enabled: canEdit,
        },
        {
            id: 'attribute-policies',
            title: t(
                'workspace.manage.attribute_policies',
                'Attribute policies'
            ),
            icon: <ScaleIcon />,
            component: AttributePoliciesTab,
            enabled: canEdit,
        },
        {
            id: 'renditions',
            title: t('workspace.manage.renditions', 'Renditions'),
            icon: <ImagesIcon />,
            component: RenditionDefinitionsTab,
            enabled: canEdit,
        },
        {
            id: 'rendition-policies',
            title: t(
                'workspace.manage.rendition_policies',
                'Rendition policies'
            ),
            icon: <FileLockIcon />,
            component: RenditionPoliciesTab,
            enabled: canEdit,
        },
        {
            id: 'asset-policies',
            title: t('workspace.manage.asset_policies', 'Asset policies'),
            icon: <ShieldCheckIcon />,
            component: AssetPoliciesTab,
            enabled: canEdit,
        },
        {
            id: 'integrations',
            title: t('workspace.manage.integrations', 'Integrations'),
            icon: <PlugIcon />,
            component: IntegrationsTab,
            enabled: canEdit,
        },
        {
            id: 'filter-rules',
            title: t('workspace.manage.filter_rules', 'Filter rules'),
            icon: <FilterIcon />,
            component: FilterRulesTab,
            enabled: canEdit,
        },
    ];

    return (
        <TabbedRouteDialog<WorkspaceTabProps>
            title={t('workspace.manage.title', 'Manage workspace')}
            subtitle={workspace.displayName ?? workspace.name}
            tabs={tabs}
            activeTab={tab}
            buildTabHref={next => routes.workspaceManage(workspaceId, next)}
            baseProps={{workspace, refresh: () => query.refetch()}}
            size="xl"
        />
    );
}

'use client';

import {useMemo} from 'react';
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
    DialogTab,
    TabbedRouteDialogShell,
} from '@/components/modals/TabbedRouteDialog';
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

/**
 * Shared by the shell and the tab content, which render in two different route
 * segments: the query key is the same, so only one request is made.
 */
function useWorkspace(workspaceId: string) {
    const upsertWorkspace = useCollectionStore(s => s.upsertWorkspace);

    return useQuery({
        queryKey: ['workspace', workspaceId],
        queryFn: async () => {
            const ws = await getWorkspace(workspaceId);
            upsertWorkspace(ws);

            return ws;
        },
    });
}

function useTabs(workspace?: Workspace): DialogTab<WorkspaceTabProps>[] {
    const {t} = useTranslation();
    const canEdit = !!workspace?.capabilities.edit;

    return [
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
            enabled: !!workspace?.capabilities.editPermissions,
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
}

export function WorkspaceManageShell({workspaceId}: {workspaceId: string}) {
    const {t} = useTranslation();
    const query = useWorkspace(workspaceId);
    const workspace = query.data;
    const tabs = useTabs(workspace);

    // Stable: every tab kept mounted receives it, and is memoized (`refetch`
    // is stable, the query result object is not)
    const {refetch} = query;
    const baseProps = useMemo(
        () =>
            workspace ? {workspace, refresh: () => void refetch()} : undefined,
        [workspace, refetch]
    );

    return (
        <TabbedRouteDialogShell
            title={t('workspace.manage.title', 'Manage workspace')}
            subtitle={workspace?.displayName ?? workspace?.name}
            tabs={tabs}
            baseProps={baseProps}
            buildTabHref={tab => routes.workspaceManage(workspaceId, tab)}
            size="xl"
            placeholder={workspace ? undefined : <FullPageLoader />}
        />
    );
}

'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    ExpandIcon,
    InfoIcon,
    PencilIcon,
    ImagesIcon,
    HistoryIcon,
    ShieldIcon,
    WorkflowIcon,
    WrenchIcon,
    DatabaseIcon,
} from 'lucide-react';
import {getAsset} from '@/lib/api/assets';
import {
    TabbedRouteDialog,
    DialogTab,
} from '@/components/modals/TabbedRouteDialog';
import {RouteDialog} from '@/components/modals/RouteDialog';
import {FullPageLoader} from '@/components/ui/loader';
import {routes} from '@/lib/routes';
import type {Asset} from '@/types/api';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {AssetInfoTab} from './tabs/AssetInfoTab';
import {AssetEditTab} from './tabs/AssetEditTab';
import {AssetRenditionsTab} from './tabs/AssetRenditionsTab';
import {AssetVersionsTab} from './tabs/AssetVersionsTab';
import {AssetPermissionsTab} from './tabs/AssetPermissionsTab';
import {AssetWorkflowTab} from './tabs/AssetWorkflowTab';
import {AssetOperationsTab} from './tabs/AssetOperationsTab';
import {ESDocumentTab} from './tabs/ESDocumentTab';
import {useAssetStore} from '@/features/assets/assetStore';
import {EmptyState} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {useRouter} from 'next/navigation';

export type AssetTabProps = {asset: Asset; refresh: () => void};

export function AssetManageRoute({
    assetId,
    tab,
}: {
    assetId: string;
    tab: string;
}) {
    const {t} = useTranslation();
    const router = useRouter();
    const {hasRole} = useAuth();
    const update = useAssetStore(s => s.update);
    const query = useQuery({
        queryKey: ['asset', assetId],
        queryFn: async () => {
            const asset = await getAsset(assetId);
            update(asset);

            return asset;
        },
        staleTime: 0,
    });
    const asset = query.data;

    if (query.isLoading || (!asset && !query.isError)) {
        return (
            <RouteDialog size="lg">
                <FullPageLoader />
            </RouteDialog>
        );
    }
    if (!asset) {
        return (
            <RouteDialog size="sm">
                <EmptyState
                    title={t(
                        'asset.view.not_found',
                        'Asset not found or not accessible'
                    )}
                    action={
                        <Button onClick={() => router.back()}>
                            {t('common.close', 'Close')}
                        </Button>
                    }
                />
            </RouteDialog>
        );
    }

    const tabs: DialogTab<AssetTabProps>[] = [
        {
            id: 'open',
            title: t('asset.manage.open', 'Open'),
            icon: <ExpandIcon />,
            component: OpenTab,
        },
        {
            id: 'info',
            title: t('asset.manage.info', 'Info'),
            icon: <InfoIcon />,
            component: AssetInfoTab,
        },
        {
            id: 'edit',
            title: t('asset.manage.edit', 'Edit'),
            icon: <PencilIcon />,
            component: AssetEditTab,
            enabled:
                asset.capabilities.editAttributes || asset.capabilities.edit,
        },
        {
            id: 'renditions',
            title: t('asset.manage.renditions', 'Renditions'),
            icon: <ImagesIcon />,
            component: AssetRenditionsTab,
        },
        {
            id: 'versions',
            title: t('asset.manage.versions', 'Versions'),
            icon: <HistoryIcon />,
            component: AssetVersionsTab,
            enabled: asset.capabilities.edit,
        },
        {
            id: 'permissions',
            title: t('asset.manage.permissions', 'Permissions'),
            icon: <ShieldIcon />,
            component: AssetPermissionsTab,
            enabled: asset.capabilities.editPermissions,
        },
        {
            id: 'workflow',
            title: t('asset.manage.workflow', 'Workflow'),
            icon: <WorkflowIcon />,
            component: AssetWorkflowTab,
            enabled: asset.capabilities.edit,
        },
        {
            id: 'operations',
            title: t('asset.manage.operations', 'Operations'),
            icon: <WrenchIcon />,
            component: AssetOperationsTab,
            enabled: asset.capabilities.edit || asset.capabilities.delete,
        },
        {
            id: 'es',
            title: t('asset.manage.es_document', 'ES Document'),
            icon: <DatabaseIcon />,
            component: ESDocumentTab,
            enabled: hasRole(AppRole.Tech),
        },
    ];

    return (
        <TabbedRouteDialog<AssetTabProps>
            title={t('asset.manage.title', 'Manage asset')}
            subtitle={asset.name}
            tabs={tabs}
            activeTab={tab}
            buildTabHref={next => routes.assetManage(assetId, next)}
            baseProps={{asset, refresh: () => query.refetch()}}
            size="xl"
        />
    );
}

function OpenTab({asset}: AssetTabProps) {
    const router = useRouter();
    const {t} = useTranslation();

    return (
        <EmptyState
            title={asset.name ?? ''}
            action={
                <Button
                    onClick={() => router.replace(routes.assetView(asset.id))}
                >
                    <ExpandIcon /> {t('asset.actions.open', 'Open')}
                </Button>
            }
        />
    );
}

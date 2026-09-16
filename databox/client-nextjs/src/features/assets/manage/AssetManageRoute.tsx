'use client';

import {ReactNode} from 'react';
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
    DialogTab,
    DialogTabContent,
    TabbedRouteDialogShell,
} from '@/components/modals/TabbedRouteDialog';
import {useCloseRoute} from '@/components/modals/RouteDialog';
import {useRouter} from 'next/navigation';
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

export type AssetTabProps = {asset: Asset; refresh: () => void};

/** Shared by the shell and the tab content: one query, one request. */
function useAsset(assetId: string) {
    const update = useAssetStore(s => s.update);

    return useQuery({
        queryKey: ['asset', assetId],
        queryFn: async () => {
            const asset = await getAsset(assetId);
            update(asset);

            return asset;
        },
        staleTime: 0,
    });
}

function useTabs(asset?: Asset): DialogTab<AssetTabProps>[] {
    const {t} = useTranslation();
    const {hasRole} = useAuth();

    return [
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
            enabled: !!(
                asset?.capabilities.editAttributes || asset?.capabilities.edit
            ),
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
            enabled: !!asset?.capabilities.edit,
        },
        {
            id: 'permissions',
            title: t('asset.manage.permissions', 'Permissions'),
            icon: <ShieldIcon />,
            component: AssetPermissionsTab,
            enabled: !!asset?.capabilities.editPermissions,
        },
        {
            id: 'workflow',
            title: t('asset.manage.workflow', 'Workflow'),
            icon: <WorkflowIcon />,
            component: AssetWorkflowTab,
            enabled: !!asset?.capabilities.edit,
        },
        {
            id: 'operations',
            title: t('asset.manage.operations', 'Operations'),
            icon: <WrenchIcon />,
            component: AssetOperationsTab,
            enabled: !!(asset?.capabilities.edit || asset?.capabilities.delete),
        },
        {
            id: 'es',
            title: t('asset.manage.es_document', 'ES Document'),
            icon: <DatabaseIcon />,
            component: ESDocumentTab,
            enabled: hasRole(AppRole.Tech),
        },
    ];
}

export function AssetManageShell({
    assetId,
    children,
}: {
    assetId: string;
    children: ReactNode;
}) {
    const {t} = useTranslation();
    const query = useAsset(assetId);
    const asset = query.data;

    const missing = !asset && !query.isLoading;

    const tabs = useTabs(asset);

    return (
        <TabbedRouteDialogShell
            title={t('asset.manage.title', 'Manage asset')}
            subtitle={asset?.name}
            tabs={tabs}
            buildTabHref={tab => routes.assetManage(assetId, tab)}
            size="xl"
            placeholder={
                asset ? undefined : missing ? (
                    <AssetNotFound />
                ) : (
                    <FullPageLoader />
                )
            }
        >
            {children}
        </TabbedRouteDialogShell>
    );
}

/** Rendered inside the dialog, so that closing uses the dialog's origin. */
function AssetNotFound() {
    const {t} = useTranslation();
    const closeRoute = useCloseRoute();

    return (
        <EmptyState
            title={t(
                'asset.view.not_found',
                'Asset not found or not accessible'
            )}
            action={
                <Button onClick={closeRoute}>
                    {t('common.close', 'Close')}
                </Button>
            }
        />
    );
}

export function AssetManageTab({assetId, tab}: {assetId: string; tab: string}) {
    const query = useAsset(assetId);
    const asset = query.data;

    const tabs = useTabs(asset);

    return (
        <DialogTabContent
            tabs={tabs}
            tab={tab}
            baseProps={
                asset ? {asset, refresh: () => void query.refetch()} : undefined
            }
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

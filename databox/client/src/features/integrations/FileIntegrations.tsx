'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import type {ApiFile, Asset, WorkspaceIntegration} from '@/types/api';
import {
    getIntegrationsOfContext,
    IntegrationContext,
} from '@/lib/api/integrations';
import {Badge} from '@/components/ui/misc';
import {IntegrationPanel} from './IntegrationPanel';
import {integrationLabel} from '@/features/integrations/integrationLabel';

/**
 * Integrations available for the displayed file: workspace integrations of
 * the `asset-view` context, filtered by supported file type.
 */
export function useFileIntegrations(
    asset: Asset,
    file: ApiFile | undefined,
    enabled: boolean
): WorkspaceIntegration[] {
    const integrations = useQuery({
        queryKey: [
            'integrations',
            'asset-view',
            asset.workspace.id,
            file?.type,
        ],
        queryFn: () =>
            getIntegrationsOfContext(
                IntegrationContext.AssetView,
                asset.workspace.id,
                {fileType: file!.type}
            ),
        enabled: enabled && !!file,
    });

    return (integrations.data?.items ?? []).filter(i => i.supported !== false);
}

/**
 * Tab of the asset side panel dedicated to one integration, run on the
 * displayed file.
 */
export function FileIntegrationTab({
    asset,
    file,
    integration,
}: {
    asset: Asset;
    file: ApiFile;
    integration: WorkspaceIntegration;
}) {
    const {t} = useTranslation();

    return (
        <div className="space-y-3" data-testid="asset-integration">
            <div className="flex min-w-0 items-center gap-2 pt-1">
                <span className="truncate text-sm font-medium">
                    {integrationLabel(integration)}
                </span>
                {integration.name ? (
                    <Badge variant="muted">
                        {integration.integrationName ?? integration.integration}
                    </Badge>
                ) : null}
            </div>
            {integration.capabilities?.use !== false ? (
                // A new file starts from a clean panel
                <IntegrationPanel
                    key={file.id}
                    integration={integration}
                    asset={asset}
                    file={file}
                />
            ) : (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'integrations.not_allowed_for_use',
                        'You are not allowed to use this integration'
                    )}
                </p>
            )}
        </div>
    );
}

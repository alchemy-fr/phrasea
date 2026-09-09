'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {PlugIcon} from 'lucide-react';
import type {ApiFile, Asset} from '@/types/api';
import {
    getIntegrationsOfContext,
    IntegrationContext,
} from '@/lib/api/integrations';
import {InlineLoader} from '@/components/ui/loader';
import {Badge} from '@/components/ui/misc';
import {IntegrationPanel} from './IntegrationPanel';

/**
 * Integrations available for the displayed file (workspace integrations of
 * the `asset-view` context, filtered by supported file type).
 */
export function FileIntegrations({asset, file}: {asset: Asset; file: ApiFile}) {
    const {t} = useTranslation();
    const integrations = useQuery({
        queryKey: ['integrations', 'asset-view', asset.workspace.id, file.type],
        queryFn: () =>
            getIntegrationsOfContext(
                IntegrationContext.AssetView,
                asset.workspace.id,
                {fileType: file.type}
            ),
    });

    if (integrations.isLoading) {
        return <InlineLoader />;
    }
    const list = (integrations.data?.items ?? []).filter(
        i => i.capabilities?.use !== false && i.supported !== false
    );
    if (list.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t(
                    'integrations.none',
                    'No integration available for this file'
                )}
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {list.map(integration => (
                <div key={integration.id} className="rounded-md border p-3">
                    <div className="mb-2 flex items-center gap-2 text-sm font-medium">
                        <PlugIcon className="size-4 text-muted-foreground" />
                        {integration.title ?? integration.name}
                        <Badge variant="muted" className="ml-auto">
                            {integration.integration}
                        </Badge>
                    </div>
                    <IntegrationPanel
                        integration={integration}
                        asset={asset}
                        file={file}
                    />
                </div>
            ))}
        </div>
    );
}

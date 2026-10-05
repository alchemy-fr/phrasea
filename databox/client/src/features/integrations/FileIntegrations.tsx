'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import type {ApiFile, Asset} from '@/types/api';
import {
    getIntegrationsOfContext,
    IntegrationContext,
} from '@/lib/api/integrations';
import {
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
    Badge,
} from '@/components/ui/misc';
import {IntegrationPanel} from './IntegrationPanel';
import {integrationLabel} from '@/features/integrations/integrationLabel';

/**
 * Integrations available for the displayed file (workspace integrations of
 * the `asset-view` context, filtered by supported file type): one section of
 * the enclosing `Accordion` per integration, titled with its name.
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

    return (
        <>
            {(integrations.data?.items ?? [])
                .filter(i => i.supported !== false)
                .map(integration => (
                    <AccordionItem
                        key={integration.id}
                        value={`integration-${integration.id}`}
                        data-testid="asset-integration"
                    >
                        <AccordionTrigger>
                            <span className="flex min-w-0 flex-1 items-center gap-2">
                                <span className="truncate">
                                    {integrationLabel(integration)}
                                </span>
                                {integration.name ? (
                                    <Badge variant="muted">
                                        {integration.integrationName ??
                                            integration.integration}
                                    </Badge>
                                ) : null}
                            </span>
                        </AccordionTrigger>
                        <AccordionContent>
                            {integration.capabilities?.use !== false ? (
                                <IntegrationPanel
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
                        </AccordionContent>
                    </AccordionItem>
                ))}
        </>
    );
}

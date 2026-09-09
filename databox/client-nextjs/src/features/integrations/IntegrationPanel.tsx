'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {PlayIcon, RefreshCwIcon, Trash2Icon} from 'lucide-react';
import {toast} from 'sonner';
import type {ApiFile, Asset, WorkspaceIntegration} from '@/types/api';
import {getIntegrationData, runIntegrationAction} from '@/lib/api/integrations';
import {Button} from '@/components/ui/button';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {InlineLoader} from '@/components/ui/loader';

/**
 * Generic integration panel: runs the `analyze` / `process` actions and lists
 * the produced data (realtime refresh on `file-{id}` channel).
 *
 * Vendor specific renderers (AWS Rekognition boxes, Remove.bg comparison,
 * photo editor) plug in here through the `integration.integration` key.
 */
export function IntegrationPanel({
    integration,
    asset,
    file,
}: {
    integration: WorkspaceIntegration;
    asset: Asset;
    file: ApiFile;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [running, setRunning] = useState<string>();
    const queryKey = ['integration-data', integration.id, file.id];

    const data = useQuery({
        queryKey,
        queryFn: ({signal}) =>
            getIntegrationData(integration.id, undefined, signal),
    });

    useChannelEvent(
        `file-${file.id}`,
        `integration:${integration.integration}`,
        () => queryClient.invalidateQueries({queryKey})
    );

    const run = async (action: string) => {
        setRunning(action);
        try {
            await runIntegrationAction(integration.id, action, {
                fileId: file.id,
                assetId: asset.id,
            });
            void queryClient.invalidateQueries({queryKey});
            toast.success(
                t('integrations.action_done', 'Action "{{action}}" started', {
                    action,
                })
            );
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setRunning(undefined);
        }
    };

    const items = (data.data?.items ?? []).filter(
        d => !d.object || (d.object as any).id === file.id
    );
    const canInteract = integration.capabilities?.interact !== false;

    return (
        <div className="space-y-2 text-sm">
            {canInteract ? (
                <div className="flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => run('analyze')}
                        loading={running === 'analyze'}
                    >
                        <PlayIcon /> {t('integrations.analyze', 'Analyze')}
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() =>
                            queryClient.invalidateQueries({queryKey})
                        }
                    >
                        <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                    </Button>
                </div>
            ) : null}
            {data.isLoading ? <InlineLoader /> : null}
            {items.length > 0 ? (
                <ul className="max-h-64 space-y-1 overflow-y-auto">
                    {items.map(d => (
                        <li
                            key={d.id}
                            className="flex items-start gap-2 rounded bg-muted/50 px-2 py-1 text-xs"
                        >
                            <span className="font-medium">{d.name}</span>
                            <span className="min-w-0 flex-1 truncate font-mono text-muted-foreground">
                                {typeof d.value === 'string'
                                    ? d.value
                                    : JSON.stringify(d.value)}
                            </span>
                            {canInteract ? (
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={() => run('delete')}
                                    aria-label={t('common.delete', 'Delete')}
                                >
                                    <Trash2Icon />
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            ) : !data.isLoading ? (
                <p className="text-xs text-muted-foreground">
                    {t('integrations.no_data', 'No data yet')}
                </p>
            ) : null}
        </div>
    );
}

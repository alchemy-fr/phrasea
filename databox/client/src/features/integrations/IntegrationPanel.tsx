'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {PlayIcon, RefreshCwIcon, Trash2Icon} from 'lucide-react';
import {toast} from 'sonner';
import type {ApiFile, Asset, WorkspaceIntegration} from '@/types/api';
import {
    IntegrationObjectType,
    runIntegrationAction,
} from '@/lib/api/integrations';
import {useIntegrationData} from './useIntegrationData';
import {Button} from '@/components/ui/button';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {InlineLoader} from '@/components/ui/loader';
import {TuiPhotoEditorPanel, tuiPhotoEditor} from './tui/TuiPhotoEditorPanel';
import {EventCategory, IntegrationEvent, track} from '@/lib/analytics/events';
import {RekognitionPanel, awsRekognition} from './rekognition/RekognitionPanel';
import {
    FaceRecognitionPanel,
    faceRecognition,
} from '@/features/faces/FaceRecognitionPanel';

type Props = {
    integration: WorkspaceIntegration;
    asset: Asset;
    file: ApiFile;
};

/**
 * Panel of an integration, for the displayed file.
 *
 * Vendor specific renderers (photo editor…) plug in here through the
 * `integration.integration` key; the others get the generic panel.
 */
export function IntegrationPanel(props: Props) {
    if (props.integration.integration === tuiPhotoEditor) {
        return <TuiPhotoEditorPanel {...props} />;
    }
    if (props.integration.integration === awsRekognition) {
        return <RekognitionPanel {...props} />;
    }
    if (props.integration.integration === faceRecognition) {
        return <FaceRecognitionPanel {...props} />;
    }

    return <GenericIntegrationPanel {...props} />;
}

/**
 * Runs the `analyze` / `process` actions and lists the produced data
 * (realtime refresh on the `file-{id}` channel).
 */
function GenericIntegrationPanel({integration, asset, file}: Props) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [running, setRunning] = useState<string>();
    const queryKey = ['integration-data', integration.id, file.id];

    const data = useIntegrationData(integration.id, {
        type: IntegrationObjectType.File,
        id: file.id,
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
            track(EventCategory.Integration, IntegrationEvent.Run, {
                name: `${integration.integration}:${action}`,
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

    const items = data.items;
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
            {data.hasMore ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="w-full"
                    loading={data.loadingMore}
                    onClick={data.loadMore}
                >
                    {t('common.load_more', 'Load more')}
                </Button>
            ) : null}
        </div>
    );
}

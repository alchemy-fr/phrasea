'use client';

import {useState} from 'react';
import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {
    ExternalLinkIcon,
    KeyRoundIcon,
    PlugIcon,
    RefreshCwIcon,
    Square,
    TriangleAlertIcon,
    UploadCloudIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {Basket, IntegrationData, WorkspaceIntegration} from '@/types/api';
import {
    getIntegrationsOfContext,
    IntegrationContext,
    IntegrationObjectType,
    runIntegrationAction,
} from '@/lib/api/integrations';
import {InlineLoader} from '@alchemy/next-ui/ui/loader';
import {Alert, Badge, Progress} from '@alchemy/next-ui/ui/misc';
import {Button} from '@alchemy/next-ui/ui/button';
import {FormRow, Input, Textarea} from '@alchemy/next-ui/ui/input';
import {Checkbox, LabeledControl} from '@alchemy/next-ui/ui/controls';
import {useModals} from '@alchemy/next-ui/modals/ModalProvider';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {basketChannel} from '@/lib/realtime/channels';
import {integrationLabel} from '@/features/integrations/integrationLabel';
import {EventCategory, IntegrationEvent, track} from '@/lib/analytics/events';
import {useIntegrationData} from '@/features/integrations/useIntegrationData';
import {useIntegrationAuth} from '@/features/integrations/useIntegrationAuth';
import {routes} from '@/lib/routes';
import {StopExposeSyncDialog} from './StopExposeSyncDialog';

type SyncStatus = 'progress' | 'cleaning' | 'complete' | 'failed';

type SyncProgress = {
    status: SyncStatus;
    done?: number;
    total?: number;
};

/**
 * An asset that Expose refused during the last synchronization: it is not published.
 */
type SkippedAsset = {
    assetId?: string;
    basketAssetId?: string;
    title?: string;
    reason?: string;
};

/**
 * The status of a synchronization from the `action` of its realtime events.
 */
function syncStatusOf(action: unknown): SyncStatus {
    switch (action) {
        case 'sync-complete':
            return 'complete';
        case 'sync-clean':
            return 'cleaning';
        case 'sync-failed':
            return 'failed';
        default:
            return 'progress';
    }
}

/**
 * Basket integrations (context `basket`), notably Phrasea Expose: OAuth
 * authorization, publication creation / sync, progress and stop.
 */
export function BasketIntegrations({basket}: {basket: Basket}) {
    const {t} = useTranslation();
    const integrations = useQuery({
        queryKey: ['integrations', 'basket'],
        queryFn: () => getIntegrationsOfContext(IntegrationContext.Basket),
    });

    if (integrations.isLoading) {
        return <InlineLoader />;
    }
    const list = integrations.data?.items ?? [];
    if (list.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t(
                    'integrations.none_basket',
                    'No integration available for baskets'
                )}
            </p>
        );
    }

    return (
        <div className="space-y-3">
            {list.map(i => (
                <div key={i.id} className="rounded-md border p-3">
                    <div className="mb-2 flex items-center gap-2 text-sm font-medium">
                        <PlugIcon className="size-4 text-muted-foreground" />
                        {integrationLabel(i)}
                        {i.name ? (
                            <Badge variant="muted" className="ml-auto">
                                {i.integrationName ?? i.integration}
                            </Badge>
                        ) : null}
                    </div>
                    {i.integration.includes('expose') ? (
                        <ExposeIntegration integration={i} basket={basket} />
                    ) : (
                        <GenericBasketIntegration
                            integration={i}
                            basket={basket}
                        />
                    )}
                </div>
            ))}
        </div>
    );
}

function ExposeIntegration({
    integration,
    basket,
}: {
    integration: WorkspaceIntegration;
    basket: Basket;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const {openModal} = useModals();
    const {authorized, authorize, loading} = useIntegrationAuth(integration);
    const queryKey = ['integration-data', integration.id, basket.id];
    const data = useIntegrationData(
        integration.id,
        {type: IntegrationObjectType.Basket, id: basket.id},
        {enabled: authorized}
    );
    const [creating, setCreating] = useState(false);
    const [form, setForm] = useState({
        title: basket.name,
        slug: '',
        description: basket.description ?? '',
        enabled: true,
        parent: '',
        profile: '',
        ignoreRoi: false,
    });
    const [progress, setProgress] = useState<Record<string, SyncProgress>>({});

    useChannelEvent(
        basketChannel(basket.id),
        `integration:${integration.integration}`,
        (e: any) => {
            if (e?.id) {
                setProgress(p => ({
                    ...p,
                    [e.id]: {
                        status: syncStatusOf(e.action),
                        // Only the progress events carry the counts
                        done: e.done ?? p[e.id]?.done,
                        total: e.total ?? p[e.id]?.total,
                    },
                }));
            }
            void queryClient.invalidateQueries({queryKey});
        }
    );

    const perform = async (
        action: string,
        payload: Record<string, unknown>
    ) => {
        await runIntegrationAction(integration.id, action, {
            basketId: basket.id,
            ...payload,
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
    };

    const run = async (action: string, payload: Record<string, unknown>) => {
        try {
            await perform(action, payload);
        } catch (e: any) {
            toast.error(e?.message);
        }
    };

    if (loading) {
        return <InlineLoader />;
    }
    if (!authorized) {
        return (
            <Button size="sm" onClick={authorize}>
                <KeyRoundIcon /> {t('integrations.authorize', 'Authorize')}
            </Button>
        );
    }
    const syncs = data.items.filter(d => d.name === 'publication' || d.keyId);

    return (
        <div className="space-y-3 text-sm">
            {syncs.map((s: IntegrationData) => {
                const v = (
                    typeof s.value === 'object' && s.value ? s.value : {}
                ) as Record<string, any>;
                const p = progress[s.id];
                const name =
                    v.title ?? (v.id ? `#${v.id.substring(0, 6)}` : s.keyId);
                const skipped: SkippedAsset[] = Array.isArray(v.skipped)
                    ? v.skipped
                    : [];
                const skippedTotal = Math.max(
                    skipped.length,
                    typeof v.skippedTotal === 'number' ? v.skippedTotal : 0
                );

                return (
                    <div
                        key={s.id}
                        className="space-y-2 rounded-md bg-muted/50 p-2"
                    >
                        <div className="flex items-center gap-2">
                            <span className="flex-1 font-medium">{name}</span>
                            {v.ignoreRoi ? (
                                <Badge
                                    variant="muted"
                                    data-testid="expose-whole-files"
                                    title={t(
                                        'integrations.expose.whole_files_help',
                                        'The regions of interest and the extracts are not applied to this publication'
                                    )}
                                >
                                    {t(
                                        'integrations.expose.whole_files',
                                        'Whole files'
                                    )}
                                </Badge>
                            ) : null}
                            {v.url ? (
                                <Button variant="ghost" size="icon-xs" asChild>
                                    <a
                                        href={v.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <ExternalLinkIcon />
                                    </a>
                                </Button>
                            ) : null}
                        </div>
                        {p ? (
                            <div
                                className="text-xs text-muted-foreground"
                                data-testid="expose-sync-status"
                            >
                                {p.status === 'complete' ? (
                                    t(
                                        'integrations.expose.sync_complete',
                                        'Sync complete!'
                                    )
                                ) : p.status === 'cleaning' ? (
                                    t(
                                        'integrations.expose.cleaning',
                                        'Cleaning assets…'
                                    )
                                ) : p.status === 'failed' ? (
                                    <span className="text-destructive">
                                        {t(
                                            'integrations.expose.sync_failed',
                                            'Sync failed'
                                        )}
                                    </span>
                                ) : (
                                    t(
                                        'integrations.expose.syncing',
                                        'Sync in progress… {{done}}/{{total}}',
                                        {
                                            done: p.done ?? 0,
                                            total: p.total ?? '?',
                                        }
                                    )
                                )}
                                {p.total && p.status !== 'failed' ? (
                                    <Progress
                                        value={Math.round(
                                            ((p.done ?? 0) / p.total) * 100
                                        )}
                                        className="mt-1"
                                    />
                                ) : null}
                            </div>
                        ) : null}
                        {skippedTotal > 0 ? (
                            <Alert
                                variant="warning"
                                icon={<TriangleAlertIcon />}
                                className="px-3 py-2 text-xs"
                                data-testid="expose-skipped"
                                title={t(
                                    'integrations.expose.skipped_title',
                                    '{{count}} asset(s) not published',
                                    {count: skippedTotal}
                                )}
                            >
                                <ul className="space-y-0.5">
                                    {skipped.map((a, i) => (
                                        <li key={a.basketAssetId ?? i}>
                                            {a.assetId ? (
                                                <Link
                                                    href={routes.assetView(
                                                        a.assetId
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {a.title || a.assetId}
                                                </Link>
                                            ) : (
                                                <span className="font-medium">
                                                    {a.title}
                                                </span>
                                            )}
                                            {a.reason ? (
                                                <span className="text-muted-foreground">
                                                    {' — '}
                                                    {a.reason}
                                                </span>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                                {skippedTotal > skipped.length ? (
                                    <p className="mt-1 text-muted-foreground">
                                        {t(
                                            'integrations.expose.skipped_more',
                                            'And {{count}} more',
                                            {
                                                count:
                                                    skippedTotal -
                                                    skipped.length,
                                            }
                                        )}
                                    </p>
                                ) : null}
                            </Alert>
                        ) : null}
                        <div className="flex flex-wrap gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => run('force-sync', {id: s.id})}
                            >
                                <RefreshCwIcon />{' '}
                                {t(
                                    'integrations.expose.force_sync',
                                    'Force sync'
                                )}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-destructive"
                                onClick={() =>
                                    openModal(StopExposeSyncDialog, {
                                        name,
                                        onConfirm: deletePublication =>
                                            perform('stop', {
                                                id: s.id,
                                                deletePublication,
                                            }),
                                    })
                                }
                            >
                                <Square className="size-3.5" />{' '}
                                {t('integrations.expose.stop', 'Stop')}
                            </Button>
                        </div>
                    </div>
                );
            })}
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
            {creating ? (
                <div className="space-y-2 rounded-md border border-dashed p-3">
                    <FormRow label={t('integrations.expose.title', 'Title')}>
                        <Input
                            value={form.title}
                            onChange={e =>
                                setForm({...form, title: e.target.value})
                            }
                        />
                    </FormRow>
                    <FormRow label={t('integrations.expose.slug', 'Slug')}>
                        <Input
                            value={form.slug}
                            onChange={e =>
                                setForm({...form, slug: e.target.value})
                            }
                        />
                    </FormRow>
                    <FormRow label={t('common.description', 'Description')}>
                        <Textarea
                            value={form.description}
                            onChange={e =>
                                setForm({...form, description: e.target.value})
                            }
                        />
                    </FormRow>
                    <FormRow
                        label={t(
                            'integrations.expose.parent',
                            'Parent publication (id)'
                        )}
                    >
                        <Input
                            value={form.parent}
                            onChange={e =>
                                setForm({...form, parent: e.target.value})
                            }
                        />
                    </FormRow>
                    <FormRow
                        label={t(
                            'integrations.expose.profile',
                            'Publication profile (id)'
                        )}
                    >
                        <Input
                            value={form.profile}
                            onChange={e =>
                                setForm({...form, profile: e.target.value})
                            }
                        />
                    </FormRow>
                    <LabeledControl label={t('common.enabled', 'Enabled')}>
                        <Checkbox
                            checked={form.enabled}
                            onCheckedChange={v =>
                                setForm({...form, enabled: v === true})
                            }
                        />
                    </LabeledControl>
                    <LabeledControl
                        label={t(
                            'integrations.expose.ignore_roi',
                            'Publish the whole files (ignore the regions of interest and the extracts)'
                        )}
                    >
                        <Checkbox
                            checked={form.ignoreRoi}
                            onCheckedChange={v =>
                                setForm({...form, ignoreRoi: v === true})
                            }
                            data-testid="expose-ignore-roi"
                        />
                    </LabeledControl>
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setCreating(false)}
                        >
                            {t('common.cancel', 'Cancel')}
                        </Button>
                        <Button
                            size="sm"
                            onClick={async () => {
                                await run('sync', {
                                    // Fixed for the life of the publication
                                    ignoreRoi: form.ignoreRoi,
                                    publication: {
                                        title: form.title,
                                        slug: form.slug || undefined,
                                        description:
                                            form.description || undefined,
                                        enabled: form.enabled,
                                        parent: form.parent || undefined,
                                        profile: form.profile || undefined,
                                    },
                                });
                                setCreating(false);
                            }}
                        >
                            <UploadCloudIcon />{' '}
                            {t(
                                'integrations.expose.create_sync',
                                'Create & sync'
                            )}
                        </Button>
                    </div>
                </div>
            ) : (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setCreating(true)}
                >
                    <UploadCloudIcon />{' '}
                    {t(
                        'integrations.expose.new_publication',
                        'New publication'
                    )}
                </Button>
            )}
        </div>
    );
}

function GenericBasketIntegration({
    integration,
    basket,
}: {
    integration: WorkspaceIntegration;
    basket: Basket;
}) {
    const {t} = useTranslation();
    const data = useIntegrationData(integration.id, {
        type: IntegrationObjectType.Basket,
        id: basket.id,
    });

    return (
        <div className="space-y-2 text-sm">
            <Button
                size="sm"
                variant="outline"
                onClick={() =>
                    runIntegrationAction(integration.id, 'process', {
                        basketId: basket.id,
                    }).then(() => {
                        track(EventCategory.Integration, IntegrationEvent.Run, {
                            name: `${integration.integration}:process`,
                        });
                        toast.success(
                            t(
                                'integrations.action_done',
                                'Action "{{action}}" started',
                                {action: 'process'}
                            )
                        );
                    })
                }
            >
                {t('integrations.run', 'Run')}
            </Button>
            {data.items.map(d => (
                <div
                    key={d.id}
                    className="rounded bg-muted/50 px-2 py-1 text-xs"
                >
                    <span className="font-medium">{d.name}</span>{' '}
                    <span className="font-mono text-muted-foreground">
                        {typeof d.value === 'string'
                            ? d.value
                            : JSON.stringify(d.value)}
                    </span>
                </div>
            ))}
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

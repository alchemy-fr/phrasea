'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {
    ExternalLinkIcon,
    KeyRoundIcon,
    PlugIcon,
    RefreshCwIcon,
    Square,
    UploadCloudIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {Basket, IntegrationData, WorkspaceIntegration} from '@/types/api';
import {
    getIntegrationData,
    getIntegrationsOfContext,
    getIntegrationTokens,
    IntegrationContext,
    runIntegrationAction,
} from '@/lib/api/integrations';
import {InlineLoader} from '@/components/ui/loader';
import {Badge, Progress} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {useConfig} from '@/lib/config/ConfigProvider';
import {getAuthClient} from '@/lib/auth/client';

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
                        {i.title ?? i.name}
                        <Badge variant="muted" className="ml-auto">
                            {i.integration}
                        </Badge>
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

function useIntegrationAuth(integration: WorkspaceIntegration) {
    const config = useConfig();
    const tokens = useQuery({
        queryKey: ['integration-tokens', integration.id],
        queryFn: () => getIntegrationTokens(integration.id),
    });
    const valid = (tokens.data?.items ?? []).some(tk => !tk.expired);

    const authorize = async () => {
        const token = await getAuthClient().getAccessToken();
        const url = `${config.apiUrl}/integrations/${integration.id}/auth?access_token=${encodeURIComponent(token ?? '')}`;
        const popup = window.open(
            url,
            'integration-auth',
            'width=600,height=700'
        );
        await new Promise<void>(resolve => {
            const timer = setInterval(() => {
                if (!popup || popup.closed) {
                    clearInterval(timer);
                    resolve();
                }
            }, 500);
        });
        await tokens.refetch();
    };

    return {authorized: valid, authorize, loading: tokens.isLoading};
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
    const {authorized, authorize, loading} = useIntegrationAuth(integration);
    const queryKey = ['integration-data', integration.id, basket.id];
    const data = useQuery({
        queryKey,
        queryFn: ({signal}) =>
            getIntegrationData(integration.id, undefined, signal),
        enabled: authorized,
    });
    const [creating, setCreating] = useState(false);
    const [form, setForm] = useState({
        title: basket.name,
        slug: '',
        description: basket.description ?? '',
        enabled: true,
        parent: '',
        profile: '',
    });
    const [progress, setProgress] = useState<
        Record<string, {status: string; done?: number; total?: number}>
    >({});

    useChannelEvent(
        `basket-${basket.id}`,
        `integration:${integration.integration}`,
        (e: any) => {
            if (e?.id) {
                setProgress(p => ({
                    ...p,
                    [e.id]: {
                        status: e.status ?? e.type ?? 'progress',
                        done: e.done ?? e.count,
                        total: e.total,
                    },
                }));
            }
            void queryClient.invalidateQueries({queryKey});
        }
    );

    const run = async (action: string, payload: Record<string, unknown>) => {
        try {
            await runIntegrationAction(integration.id, action, {
                basketId: basket.id,
                ...payload,
            });
            void queryClient.invalidateQueries({queryKey});
            toast.success(
                t('integrations.action_done', 'Action "{{action}}" started', {
                    action,
                })
            );
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
    const syncs = (data.data?.items ?? []).filter(
        d => d.name === 'publication' || d.keyId
    );

    return (
        <div className="space-y-3 text-sm">
            {syncs.map((s: IntegrationData) => {
                const v = (
                    typeof s.value === 'object' && s.value ? s.value : {}
                ) as Record<string, any>;
                const p = progress[s.id];

                return (
                    <div
                        key={s.id}
                        className="space-y-2 rounded-md bg-muted/50 p-2"
                    >
                        <div className="flex items-center gap-2">
                            <span className="flex-1 font-medium">
                                {v.title ?? v.slug ?? s.keyId}
                            </span>
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
                            <div className="text-xs text-muted-foreground">
                                {p.status === 'complete' || p.status === 'done'
                                    ? t(
                                          'integrations.expose.sync_complete',
                                          'Sync complete!'
                                      )
                                    : p.status === 'cleaning'
                                      ? t(
                                            'integrations.expose.cleaning',
                                            'Cleaning assets…'
                                        )
                                      : t(
                                            'integrations.expose.syncing',
                                            'Sync in progress… {{done}}/{{total}}',
                                            {
                                                done: p.done ?? 0,
                                                total: p.total ?? '?',
                                            }
                                        )}
                                {p.total ? (
                                    <Progress
                                        value={Math.round(
                                            ((p.done ?? 0) / p.total) * 100
                                        )}
                                        className="mt-1"
                                    />
                                ) : null}
                            </div>
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
                                    run('stop', {
                                        id: s.id,
                                        deletePublication: window.confirm(
                                            t(
                                                'integrations.expose.also_delete',
                                                'Also delete the publication?'
                                            )
                                        ),
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
    const data = useQuery({
        queryKey: ['integration-data', integration.id, basket.id],
        queryFn: ({signal}) =>
            getIntegrationData(integration.id, undefined, signal),
    });

    return (
        <div className="space-y-2 text-sm">
            <Button
                size="sm"
                variant="outline"
                onClick={() =>
                    runIntegrationAction(integration.id, 'process', {
                        basketId: basket.id,
                    }).then(() =>
                        toast.success(
                            t(
                                'integrations.action_done',
                                'Action "{{action}}" started',
                                {action: 'process'}
                            )
                        )
                    )
                }
            >
                {t('integrations.run', 'Run')}
            </Button>
            {data.data?.items.map(d => (
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
        </div>
    );
}

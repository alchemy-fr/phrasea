'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery, useQuery} from '@tanstack/react-query';
import {
    ArrowLeftIcon,
    CheckCircle2Icon,
    CircleDashedIcon,
    ClockIcon,
    PlayIcon,
    PlusIcon,
    RefreshCwIcon,
    XCircleIcon,
    BanIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import {OperationTaskStatus} from '@/types/api';
import {
    getOperationTask,
    getOperationTasks,
    runOperationTask,
} from '@/lib/api/misc';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {Button} from '@/components/ui/button';
import {Badge, EmptyState, Skeleton} from '@/components/ui/misc';
import {FormRow, Input} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {CopyButton} from '@/components/ui/copy-button';
import {formatDateTime, formatDuration} from '@/lib/utils/format';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {
    useDefinitionsStore,
    workspaceIdOf,
} from '@/features/attributes/definitionsStore';
import {useEffect} from 'react';
import {cn} from '@/lib/utils/cn';

type TaskField = {
    name: string;
    label: string;
    type: 'workspace' | 'attribute' | 'locale' | 'text';
    required?: boolean;
};
type TaskType = {
    id: string;
    label: string;
    description: string;
    fields: TaskField[];
};

function useTaskTypes(): TaskType[] {
    const {t} = useTranslation();

    return [
        {
            id: 'switch-attribute-locale',
            label: t('tasks.switch_locale', 'Switch attribute locales'),
            description: t(
                'tasks.switch_locale_desc',
                'Changes the locale of existing attribute values.'
            ),
            fields: [
                {
                    name: 'workspaceId',
                    label: t('asset.info.workspace', 'Workspace'),
                    type: 'workspace',
                    required: true,
                },
                {
                    name: 'attributeDefinitionId',
                    label: t('common.attribute', 'Attribute'),
                    type: 'attribute',
                    required: true,
                },
                {
                    name: 'fromLocale',
                    label: t('tasks.from_locale', 'From locale'),
                    type: 'locale',
                    required: true,
                },
                {
                    name: 'toLocale',
                    label: t('tasks.to_locale', 'To locale'),
                    type: 'locale',
                    required: true,
                },
            ],
        },
        {
            id: 'index-assets',
            label: t('tasks.index_assets', 'Index assets'),
            description: t(
                'tasks.index_assets_desc',
                'Re-index the assets in Elasticsearch.'
            ),
            fields: [
                {
                    name: 'workspaceId',
                    label: t('asset.info.workspace', 'Workspace'),
                    type: 'workspace',
                },
            ],
        },
        {
            id: 'ingest-workspace-assets',
            label: t('tasks.ingest', 'Ingest workspace assets'),
            description: t(
                'tasks.ingest_desc',
                'Re-run the ingestion workflow on every asset of a workspace.'
            ),
            fields: [
                {
                    name: 'workspaceId',
                    label: t('asset.info.workspace', 'Workspace'),
                    type: 'workspace',
                    required: true,
                },
            ],
        },
        {
            id: 'store-fallback-as-attribute',
            label: t('tasks.store_fallback', 'Store fallback as attribute'),
            description: t(
                'tasks.store_fallback_desc',
                'Persists the computed fallback value as a real attribute value.'
            ),
            fields: [
                {
                    name: 'workspaceId',
                    label: t('asset.info.workspace', 'Workspace'),
                    type: 'workspace',
                    required: true,
                },
                {
                    name: 'attributeDefinitionId',
                    label: t('common.attribute', 'Attribute'),
                    type: 'attribute',
                    required: true,
                },
            ],
        },
        {
            id: 'recompute-initial-values',
            label: t('tasks.recompute_initial', 'Recompute initial values'),
            description: t(
                'tasks.recompute_initial_desc',
                'Recomputes the initial value of an attribute.'
            ),
            fields: [
                {
                    name: 'workspaceId',
                    label: t('asset.info.workspace', 'Workspace'),
                    type: 'workspace',
                    required: true,
                },
                {
                    name: 'attributeDefinitionId',
                    label: t('common.attribute', 'Attribute'),
                    type: 'attribute',
                    required: true,
                },
            ],
        },
    ];
}

function StatusBadge({status}: {status: OperationTaskStatus}) {
    const {t} = useTranslation();
    switch (status) {
        case OperationTaskStatus.Completed:
            return (
                <Badge variant="success">
                    <CheckCircle2Icon />{' '}
                    {t('tasks.status.completed', 'Completed')}
                </Badge>
            );
        case OperationTaskStatus.Failed:
            return (
                <Badge variant="destructive">
                    <XCircleIcon /> {t('tasks.status.failed', 'Failed')}
                </Badge>
            );
        case OperationTaskStatus.Cancelled:
            return (
                <Badge variant="muted">
                    <BanIcon /> {t('tasks.status.cancelled', 'Cancelled')}
                </Badge>
            );
        case OperationTaskStatus.InProgress:
            return (
                <Badge variant="warning">
                    <CircleDashedIcon className="animate-spin" />{' '}
                    {t('tasks.status.in_progress', 'In progress')}
                </Badge>
            );
        default:
            return (
                <Badge variant="muted">
                    <ClockIcon /> {t('tasks.status.pending', 'Pending')}
                </Badge>
            );
    }
}

/**
 * Operation tasks (databox-admin): history, catalog, run form and details.
 */
export function OperationTasksScreen() {
    const {t, i18n} = useTranslation();
    const {hasRole} = useAuth();
    const [view, setView] = useState<
        | {type: 'list'}
        | {type: 'catalog'}
        | {type: 'run'; task: TaskType}
        | {type: 'details'; id: string}
    >({type: 'list'});
    const tasks = useInfiniteQuery({
        queryKey: ['operation-tasks'],
        queryFn: ({pageParam}) => getOperationTasks(pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const types = useTaskTypes();

    return (
        <RequireAuth>
            {!hasRole(AppRole.DataboxAdmin) && !hasRole(AppRole.Admin) ? (
                <EmptyState
                    className="h-full"
                    title={t(
                        'common.forbidden',
                        'You are not allowed to access this page'
                    )}
                />
            ) : (
                <div className="mx-auto w-full max-w-4xl space-y-4 overflow-y-auto p-4">
                    <div className="flex items-center gap-2">
                        {view.type !== 'list' ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setView({type: 'list'})}
                            >
                                <ArrowLeftIcon /> {t('common.back', 'Back')}
                            </Button>
                        ) : null}
                        <h1 className="flex-1 text-lg font-semibold">
                            {t('tasks.title', 'Operation tasks')}
                        </h1>
                        {view.type === 'list' ? (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => tasks.refetch()}
                                >
                                    <RefreshCwIcon />{' '}
                                    {t('common.refresh', 'Refresh')}
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={() => setView({type: 'catalog'})}
                                >
                                    <PlusIcon /> {t('tasks.new', 'New task')}
                                </Button>
                            </>
                        ) : null}
                    </div>

                    {view.type === 'list' ? (
                        <div className="space-y-2">
                            {tasks.isLoading
                                ? [...Array(3)].map((_, i) => (
                                      <Skeleton key={i} className="h-16" />
                                  ))
                                : null}
                            {tasks.data?.pages
                                .flatMap(p => p.items)
                                .map(task => (
                                    <button
                                        key={task.id}
                                        type="button"
                                        className="flex w-full items-center gap-3 rounded-md border bg-card p-3 text-left text-sm hover:bg-accent/40"
                                        onClick={() =>
                                            setView({
                                                type: 'details',
                                                id: task.id,
                                            })
                                        }
                                    >
                                        {task.status ===
                                        OperationTaskStatus.InProgress ? (
                                            <ProgressRing
                                                value={task.progression ?? 0}
                                            />
                                        ) : null}
                                        <div className="min-w-0 flex-1">
                                            <div className="font-medium">
                                                {types.find(
                                                    tp => tp.id === task.task
                                                )?.label ?? task.task}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {typeof task.owner === 'object'
                                                    ? task.owner.username
                                                    : ''}{' '}
                                                ·{' '}
                                                {formatDateTime(
                                                    task.createdAt,
                                                    'medium',
                                                    i18n.language
                                                )}
                                                {task.startedAt && task.endedAt
                                                    ? ` · ${formatDuration((new Date(task.endedAt).getTime() - new Date(task.startedAt).getTime()) / 1000, 'humanized')}`
                                                    : ''}
                                                {task.remaining
                                                    ? ` · ${t('tasks.remaining', 'remaining')}: ${task.remaining}`
                                                    : ''}
                                            </div>
                                        </div>
                                        <StatusBadge status={task.status} />
                                    </button>
                                ))}
                            {tasks.data?.pages[0]?.items.length === 0 ? (
                                <EmptyState
                                    title={t('tasks.empty', 'No task yet')}
                                />
                            ) : null}
                            {tasks.hasNextPage ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => tasks.fetchNextPage()}
                                    loading={tasks.isFetchingNextPage}
                                >
                                    {t('common.load_more', 'Load more')}
                                </Button>
                            ) : null}
                        </div>
                    ) : null}

                    {view.type === 'catalog' ? (
                        <div className="grid gap-3 sm:grid-cols-2">
                            {types.map(tp => (
                                <button
                                    key={tp.id}
                                    type="button"
                                    className="rounded-md border bg-card p-4 text-left hover:border-primary hover:bg-accent/40"
                                    onClick={() =>
                                        setView({type: 'run', task: tp})
                                    }
                                >
                                    <div className="mb-1 font-medium">
                                        {tp.label}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {tp.description}
                                    </div>
                                </button>
                            ))}
                        </div>
                    ) : null}

                    {view.type === 'run' ? (
                        <RunTaskForm
                            task={view.task}
                            onRun={() => {
                                void tasks.refetch();
                                setView({type: 'list'});
                            }}
                        />
                    ) : null}

                    {view.type === 'details' ? (
                        <TaskDetails id={view.id} types={types} />
                    ) : null}
                </div>
            )}
        </RequireAuth>
    );
}

function ProgressRing({value}: {value: number}) {
    const pct = Math.round(value * 100);

    return (
        <div className="relative size-10 shrink-0">
            <svg viewBox="0 0 36 36" className="size-10 -rotate-90">
                <circle
                    cx="18"
                    cy="18"
                    r="15"
                    className="fill-none stroke-muted"
                    strokeWidth="4"
                />
                <circle
                    cx="18"
                    cy="18"
                    r="15"
                    className="fill-none stroke-primary"
                    strokeWidth="4"
                    strokeDasharray={`${pct * 0.942} 100`}
                    strokeLinecap="round"
                />
            </svg>
            <span className="absolute inset-0 flex items-center justify-center text-[10px] font-semibold">
                {pct}%
            </span>
        </div>
    );
}

function RunTaskForm({task, onRun}: {task: TaskType; onRun: () => void}) {
    const {t} = useTranslation();
    const [payload, setPayload] = useState<Record<string, string>>({});
    const [running, setRunning] = useState(false);
    const {workspaces, loadWorkspaces} = useCollectionStore();
    const {definitions, loadWorkspace} = useDefinitionsStore();
    useEffect(() => {
        void loadWorkspaces();
    }, [loadWorkspaces]);
    useEffect(() => {
        if (payload.workspaceId) {
            void loadWorkspace(payload.workspaceId);
        }
    }, [payload.workspaceId, loadWorkspace]);
    const workspace = workspaces.find(w => w.id === payload.workspaceId);
    const valid = task.fields.every(f => !f.required || payload[f.name]);

    const run = async () => {
        setRunning(true);
        try {
            await runOperationTask({task: task.id, payload});
            toast.success(t('tasks.started', 'Task started'));
            onRun();
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setRunning(false);
        }
    };

    return (
        <div className="max-w-xl space-y-3 rounded-md border bg-card p-4">
            <h2 className="font-semibold">{task.label}</h2>
            <p className="text-sm text-muted-foreground">{task.description}</p>
            {task.fields.map(f => (
                <FormRow
                    key={f.name}
                    label={`${f.label}${f.required ? ' *' : ''}`}
                >
                    {f.type === 'workspace' ? (
                        <SimpleSelect
                            value={payload.workspaceId}
                            onValueChange={v =>
                                setPayload({...payload, workspaceId: v})
                            }
                            options={workspaces.map(w => ({
                                value: w.id,
                                label: w.displayName ?? w.name,
                            }))}
                            placeholder={t('common.select', 'Select…')}
                        />
                    ) : f.type === 'attribute' ? (
                        <SimpleSelect
                            value={payload[f.name]}
                            onValueChange={v =>
                                setPayload({...payload, [f.name]: v})
                            }
                            disabled={!payload.workspaceId}
                            options={definitions
                                .filter(
                                    d =>
                                        workspaceIdOf(d) === payload.workspaceId
                                )
                                .map(d => ({
                                    value: d.id,
                                    label: d.displayName ?? d.name,
                                }))}
                            placeholder={t('common.select', 'Select…')}
                        />
                    ) : f.type === 'locale' ? (
                        <SimpleSelect
                            value={payload[f.name]}
                            onValueChange={v =>
                                setPayload({...payload, [f.name]: v})
                            }
                            options={(workspace?.enabledLocales ?? []).map(
                                l => ({value: l, label: l})
                            )}
                            placeholder={t('common.select', 'Select…')}
                        />
                    ) : (
                        <Input
                            value={payload[f.name] ?? ''}
                            onChange={e =>
                                setPayload({
                                    ...payload,
                                    [f.name]: e.target.value,
                                })
                            }
                        />
                    )}
                </FormRow>
            ))}
            <div className="flex justify-end">
                <Button onClick={run} disabled={!valid} loading={running}>
                    <PlayIcon /> {t('tasks.run', 'Run')}
                </Button>
            </div>
        </div>
    );
}

function TaskDetails({id, types}: {id: string; types: TaskType[]}) {
    const {t, i18n} = useTranslation();
    const task = useQuery({
        queryKey: ['operation-task', id],
        queryFn: () => getOperationTask(id),
        refetchInterval: q =>
            q.state.data?.status === OperationTaskStatus.InProgress
                ? 3000
                : false,
    });
    const d = task.data;
    if (!d) {
        return <Skeleton className="h-40" />;
    }
    const payload = JSON.stringify(d.payload, null, 2);

    return (
        <div className="space-y-3 rounded-md border bg-card p-4 text-sm">
            <div className="flex items-center gap-3">
                <h2 className="flex-1 font-semibold">
                    {types.find(tp => tp.id === d.task)?.label ?? d.task}
                </h2>
                <StatusBadge status={d.status} />
            </div>
            <dl className="grid gap-2 sm:grid-cols-2">
                <Row label={t('asset.info.owner', 'Owner')}>
                    {typeof d.owner === 'object' ? d.owner.username : d.owner}
                </Row>
                <Row label={t('asset.info.created_at', 'Created at')}>
                    {formatDateTime(d.createdAt, 'medium', i18n.language)}
                </Row>
                <Row label={t('tasks.started_at', 'Started at')}>
                    {d.startedAt
                        ? formatDateTime(d.startedAt, 'medium', i18n.language)
                        : '—'}
                </Row>
                <Row label={t('tasks.ended_at', 'Ended at')}>
                    {d.endedAt
                        ? formatDateTime(d.endedAt, 'medium', i18n.language)
                        : '—'}
                </Row>
                <Row label={t('tasks.duration', 'Duration')}>
                    {d.startedAt && d.endedAt
                        ? formatDuration(
                              (new Date(d.endedAt).getTime() -
                                  new Date(d.startedAt).getTime()) /
                                  1000,
                              'humanized'
                          )
                        : '—'}
                </Row>
                <Row label={t('tasks.items', 'Items')}>
                    {d.progress ?? '—'} / {d.itemTotal ?? '—'}
                </Row>
            </dl>
            <div>
                <div className="mb-1 flex items-center gap-1 text-xs text-muted-foreground">
                    {t('tasks.payload', 'Payload')}{' '}
                    <CopyButton value={payload} />
                </div>
                <pre className="rounded bg-muted p-2 font-mono text-xs">
                    {payload}
                </pre>
            </div>
            {d.output ? (
                <div>
                    <div className="mb-1 text-xs text-muted-foreground">
                        {t('tasks.output', 'Output')}
                    </div>
                    <pre
                        className={cn(
                            'max-h-64 overflow-auto rounded p-2 font-mono text-xs',
                            d.status === OperationTaskStatus.Failed
                                ? 'bg-destructive/10 text-destructive'
                                : 'bg-muted'
                        )}
                    >
                        {d.output}
                    </pre>
                </div>
            ) : null}
        </div>
    );
}

function Row({label, children}: {label: string; children: React.ReactNode}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

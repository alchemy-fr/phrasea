'use client';

import {getUserDisplayName} from '@/lib/users';
import {useState} from 'react';
import Link from 'next/link';
import {useParams, useRouter} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {
    useInfiniteQuery,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {
    ArrowLeftIcon,
    CheckCircle2Icon,
    CircleDashedIcon,
    ClockIcon,
    PlayIcon,
    PlusIcon,
    RefreshCwIcon,
    Trash2Icon,
    XCircleIcon,
    BanIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import {OperationTask, OperationTaskStatus} from '@/types/api';
import {
    cancelOperationTask,
    deleteOperationTask,
    getOperationTask,
    getOperationTasks,
    runOperationTask,
} from '@/lib/api/misc';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {Button} from '@alchemy/ui/ui/button';
import {useConfirm} from '@alchemy/ui/ui/confirm';
import {Badge, EmptyState, Skeleton} from '@alchemy/ui/ui/misc';
import {FormRow, Input} from '@alchemy/ui/ui/input';
import {SimpleSelect} from '@alchemy/ui/ui/select';
import {CopyButton} from '@/components/ui/copy-button';
import {formatDateTime, formatDuration} from '@/lib/utils/format';
import {useWorkspace} from '@/features/collections/collectionStore';
import {
    AttributeDefinitionSelect,
    WorkspaceSelect,
} from '@/components/form/selects';
import {cn} from '@alchemy/ui/utils/cn';
import {usePageTrail} from '@/components/layout/layoutStore';
import {routes} from '@/lib/routes';
import {ScrollablePage} from '@/components/layout/ScrollablePage';
import {parseTaskPath, TaskView} from './parseTaskPath';

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
            id: 'switch_attribute_locales',
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
                    name: 'definitionId',
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
            id: 'index_assets',
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
            id: 'ingest_workspace_assets',
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
            id: 'import_files',
            label: t('tasks.import_files', 'Import files'),
            description: t(
                'tasks.import_files_desc',
                'Fetch every file of a workspace still referenced by URL and store it on the main storage.'
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
            id: 'store_fallback_attributes',
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
                    name: 'definitionId',
                    label: t('common.attribute', 'Attribute'),
                    type: 'attribute',
                    required: true,
                },
            ],
        },
        {
            id: 'recompute_initial_values',
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
                    name: 'definitionId',
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

const listQueryKey = ['operation-tasks'];

function backRoute(view: TaskView): string | undefined {
    switch (view.type) {
        case 'list':
            return undefined;
        case 'run':
            return routes.operationTaskNew();
        default:
            return routes.operationTasks();
    }
}

/**
 * Operation tasks (databox-admin): history, catalog, run form and details,
 * each one at its own URL (see `parseTaskPath`).
 */
export function OperationTasksScreen() {
    const {t} = useTranslation();
    const {hasRole} = useAuth();
    const params = useParams<{path?: string[]}>();
    const view = parseTaskPath(params?.path);
    const types = useTaskTypes();
    const back = backRoute(view);
    const runType =
        view.type === 'run'
            ? types.find(tp => tp.id === view.taskType)
            : undefined;

    // Where the user is, shown in the top bar with the way back to the assets
    usePageTrail(t('tasks.title', 'Operation tasks'), routes.operationTasks());

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
                <ScrollablePage className="max-w-4xl">
                    <div className="flex items-center gap-2">
                        {back ? (
                            <Button asChild variant="ghost" size="sm">
                                <Link href={back}>
                                    <ArrowLeftIcon /> {t('common.back', 'Back')}
                                </Link>
                            </Button>
                        ) : null}
                        <h1 className="flex-1 text-lg font-semibold">
                            {t('tasks.title', 'Operation tasks')}
                        </h1>
                        {view.type === 'list' ? <ListActions /> : null}
                    </div>

                    {view.type === 'list' ? <TaskList types={types} /> : null}

                    {view.type === 'catalog' ? (
                        <TaskCatalog types={types} />
                    ) : null}

                    {view.type === 'run' && runType ? (
                        <RunTaskForm key={runType.id} task={runType} />
                    ) : null}

                    {view.type === 'details' ? (
                        <TaskDetails key={view.id} id={view.id} types={types} />
                    ) : null}

                    {view.type === 'not_found' ||
                    (view.type === 'run' && !runType) ? (
                        <EmptyState
                            title={t('tasks.not_found', 'Task not found')}
                        />
                    ) : null}
                </ScrollablePage>
            )}
        </RequireAuth>
    );
}

function useTaskList() {
    return useInfiniteQuery({
        queryKey: listQueryKey,
        queryFn: ({pageParam}) => getOperationTasks(pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
}

function ListActions() {
    const {t} = useTranslation();
    const tasks = useTaskList();

    return (
        <>
            <Button variant="outline" size="sm" onClick={() => tasks.refetch()}>
                <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
            </Button>
            <Button asChild size="sm">
                <Link href={routes.operationTaskNew()}>
                    <PlusIcon /> {t('tasks.new', 'New task')}
                </Link>
            </Button>
        </>
    );
}

function TaskList({types}: {types: TaskType[]}) {
    const {t, i18n} = useTranslation();
    const tasks = useTaskList();

    return (
        <div className="space-y-2">
            {tasks.isLoading
                ? [...Array(3)].map((_, i) => (
                      <Skeleton key={i} className="h-16" />
                  ))
                : null}
            {tasks.data?.pages
                .flatMap(p => p.items)
                .map(task => (
                    <Link
                        key={task.id}
                        href={routes.operationTaskDetails(task.id)}
                        data-testid="task-row"
                        className="flex w-full items-center gap-3 rounded-md border bg-card p-3 text-left text-sm hover:bg-accent/40"
                    >
                        {task.status === OperationTaskStatus.InProgress ? (
                            <ProgressRing value={task.progression ?? 0} />
                        ) : null}
                        <div className="min-w-0 flex-1">
                            <div className="font-medium">
                                {types.find(tp => tp.id === task.task)?.label ??
                                    task.task}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {typeof task.owner === 'object'
                                    ? getUserDisplayName(task.owner)
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
                    </Link>
                ))}
            {tasks.data?.pages[0]?.items.length === 0 ? (
                <EmptyState title={t('tasks.empty', 'No task yet')} />
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
    );
}

function TaskCatalog({types}: {types: TaskType[]}) {
    const {t} = useTranslation();

    return (
        <div className="space-y-3">
            <div data-testid="task-catalog-intro">
                <h2 className="font-semibold">{t('tasks.new', 'New task')}</h2>
                <p className="text-sm text-muted-foreground">
                    {t(
                        'tasks.catalog.intro',
                        'You are about to start a new task. Choose the one to run from the catalog below.'
                    )}
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                {types.map(tp => (
                    <Link
                        key={tp.id}
                        href={routes.operationTaskRun(tp.id)}
                        className="rounded-md border bg-card p-4 text-left hover:border-primary hover:bg-accent/40"
                    >
                        <div className="mb-1 font-medium">{tp.label}</div>
                        <div className="text-sm text-muted-foreground">
                            {tp.description}
                        </div>
                    </Link>
                ))}
            </div>
        </div>
    );
}

/**
 * @param value progression in percent (0–100)
 */
function ProgressRing({value}: {value: number}) {
    const pct = Math.min(100, Math.max(0, Math.round(value)));

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

function RunTaskForm({task}: {task: TaskType}) {
    const {t} = useTranslation();
    const router = useRouter();
    const queryClient = useQueryClient();
    const [payload, setPayload] = useState<Record<string, string>>({});
    const [running, setRunning] = useState(false);
    const workspace = useWorkspace(payload.workspaceId);
    const valid = task.fields.every(f => !f.required || payload[f.name]);

    const run = async () => {
        setRunning(true);
        try {
            const created = await runOperationTask({task: task.id, payload});
            toast.success(t('tasks.started', 'Task started'));
            void queryClient.invalidateQueries({queryKey: listQueryKey});
            router.push(routes.operationTaskDetails(created.id));
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
                        <WorkspaceSelect
                            value={payload.workspaceId}
                            onChange={v => {
                                const {workspaceId: _ws, ...rest} = payload;
                                setPayload(
                                    v ? {...rest, workspaceId: v} : rest
                                );
                            }}
                            placeholder={t('common.select', 'Select…')}
                        />
                    ) : f.type === 'attribute' ? (
                        <AttributeDefinitionSelect
                            key={payload.workspaceId}
                            workspaceId={payload.workspaceId}
                            value={payload[f.name]}
                            onChange={v => {
                                const {[f.name]: _v, ...rest} = payload;
                                setPayload(v ? {...rest, [f.name]: v} : rest);
                            }}
                            disabled={!payload.workspaceId}
                            placeholder={t('common.select', 'Select…')}
                        />
                    ) : f.type === 'locale' ? (
                        <SimpleSelect
                            value={payload[f.name]}
                            onValueChange={v =>
                                setPayload({...payload, [f.name]: v})
                            }
                            options={[
                                {
                                    value: '_',
                                    label: t('tasks.no_locale', 'No locale'),
                                },
                                ...(workspace?.enabledLocales ?? []).map(l => ({
                                    value: l,
                                    label: l,
                                })),
                            ]}
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
    const confirm = useConfirm();
    const router = useRouter();
    const queryClient = useQueryClient();
    const task = useQuery({
        queryKey: ['operation-task', id],
        queryFn: () => getOperationTask(id),
        refetchInterval: q =>
            q.state.data && isCancellable(q.state.data) ? 3000 : false,
    });
    const d = task.data;
    if (task.isError) {
        return <EmptyState title={t('tasks.not_found', 'Task not found')} />;
    }
    if (!d) {
        return <Skeleton className="h-40" />;
    }

    const cancel = async () => {
        if (
            await confirm({
                title: t('tasks.cancel', 'Cancel the task'),
                description: t(
                    'tasks.cancel_confirm',
                    'The task stops at its next step: what it already did is kept.'
                ),
                confirmLabel: t('tasks.cancel', 'Cancel the task'),
                onConfirm: async () => {
                    queryClient.setQueryData<OperationTask>(
                        ['operation-task', id],
                        await cancelOperationTask(id)
                    );
                },
            })
        ) {
            void queryClient.invalidateQueries({queryKey: listQueryKey});
            toast.success(t('tasks.cancelled_toast', 'Task cancelled'));
        }
    };

    const remove = async () => {
        if (
            await confirm({
                title: t('tasks.delete', 'Delete the task'),
                description: t(
                    'tasks.delete_confirm',
                    'The task and its output will be removed from the history.'
                ),
                confirmLabel: t('common.delete', 'Delete'),
                destructive: true,
                onConfirm: () => deleteOperationTask(id),
            })
        ) {
            queryClient.removeQueries({queryKey: ['operation-task', id]});
            void queryClient.invalidateQueries({queryKey: listQueryKey});
            toast.success(t('tasks.deleted_toast', 'Task deleted'));
            router.push(routes.operationTasks());
        }
    };

    const payload = JSON.stringify(d.payload, null, 2);

    return (
        <div className="space-y-3 rounded-md border bg-card p-4 text-sm">
            <div className="flex items-center gap-3">
                {d.status === OperationTaskStatus.InProgress ? (
                    <ProgressRing value={d.progression ?? 0} />
                ) : null}
                <h2 className="flex-1 font-semibold">
                    {types.find(tp => tp.id === d.task)?.label ?? d.task}
                </h2>
                <StatusBadge status={d.status} />
                {isCancellable(d) ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={cancel}
                        data-testid="task-cancel"
                    >
                        <BanIcon /> {t('tasks.cancel', 'Cancel the task')}
                    </Button>
                ) : null}
                {isTerminated(d) ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={remove}
                        data-testid="task-delete"
                    >
                        <Trash2Icon /> {t('tasks.delete', 'Delete the task')}
                    </Button>
                ) : null}
            </div>
            <dl className="grid gap-2 sm:grid-cols-2">
                <Row label={t('asset.info.owner', 'Owner')}>
                    {typeof d.owner === 'object'
                        ? getUserDisplayName(d.owner)
                        : d.owner}
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
                <Row label={t('tasks.progression', 'Progression')}>
                    {d.progression != null ? `${d.progression}%` : '—'}
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

function isCancellable(task: OperationTask): boolean {
    return [
        OperationTaskStatus.Pending,
        OperationTaskStatus.InProgress,
    ].includes(task.status);
}

function isTerminated(task: OperationTask): boolean {
    return [
        OperationTaskStatus.Completed,
        OperationTaskStatus.Failed,
        OperationTaskStatus.Cancelled,
    ].includes(task.status);
}

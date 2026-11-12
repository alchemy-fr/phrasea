'use client';

import {useCallback, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import Link from 'next/link';
import {usePathname, useRouter, useSearchParams} from 'next/navigation';
import {useInfiniteQuery, useQueries} from '@tanstack/react-query';
import {
    ChevronDownIcon,
    ChevronRightIcon,
    LinkIcon,
    RefreshCwIcon,
    ScrollTextIcon,
    XIcon,
} from 'lucide-react';
import {type AuditLog, AuditLogActorType, type User} from '@/types/api';
import {type AuditLogFilters, getAuditLogs} from '@/lib/api/auditLogs';
import {getUsers} from '@/lib/api/misc';
import {RequireAuth} from '@/lib/auth/RequireAuth';
import {AppRole, useAuth} from '@/lib/auth/AuthProvider';
import {Button} from '@alchemy/next-ui/ui/button';
import {Badge, EmptyState, Skeleton} from '@alchemy/next-ui/ui/misc';
import {Input, Label} from '@alchemy/next-ui/ui/input';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {
    AsyncCombobox,
    type ComboOption,
} from '@alchemy/next-ui/form/AsyncCombobox';
import {UserSelect, WorkspaceSelect} from '@/components/form/selects';
import {usePageTrail} from '@/components/layout/layoutStore';
import {formatDateTime} from '@/lib/utils/format';
import {getUserDisplayName} from '@/lib/users';
import {routes} from '@/lib/routes';
import {AuditLogDetails} from './AuditLogDetails';
import {
    AUDIT_LOG_ACTIONS,
    AUDIT_LOG_OBJECT_TYPES,
    auditLogActionLabel,
    auditLogObjectHref,
    auditLogObjectTypeLabel,
} from './auditLogLabels';
import {ScrollablePage} from '@/components/layout/ScrollablePage';

const ALL = '_all';

/** The filters of the screen, kept in the URL (`?action=…&workspaceId=…`) */
type ScreenFilters = AuditLogFilters & {
    /** Day (YYYY-MM-DD) */
    from?: string;
    to?: string;
};

const SCALAR_PARAMS = [
    'actorType',
    'objectType',
    'objectId',
    'userId',
    'workspaceId',
    'correlationId',
    'from',
    'to',
] as const;

function readFilters(params: URLSearchParams): ScreenFilters {
    const filters: ScreenFilters = {};
    const actions = params.getAll('action').filter(Boolean);
    if (actions.length) {
        filters.actions = actions;
    }
    for (const key of SCALAR_PARAMS) {
        const value = params.get(key);
        if (value) {
            (filters as Record<string, string>)[key] = value;
        }
    }

    return filters;
}

function writeFilters(filters: ScreenFilters): string {
    const params = new URLSearchParams();
    filters.actions?.forEach(a => params.append('action', a));
    for (const key of SCALAR_PARAMS) {
        const value = filters[key];
        if (value) {
            params.set(key, value);
        }
    }

    return params.toString();
}

function toApiFilters({from, to, ...filters}: ScreenFilters): AuditLogFilters {
    return {
        ...filters,
        dateAfter: from
            ? new Date(`${from}T00:00:00`).toISOString()
            : undefined,
        dateBefore: to
            ? new Date(`${to}T23:59:59.999`).toISOString()
            : undefined,
    };
}

const actionOptions: ComboOption[] = Object.keys(AUDIT_LOG_ACTIONS).map(
    key => ({value: key, label: AUDIT_LOG_ACTIONS[key]})
);

/**
 * The audit log of the instance (admins): who did what, the last entry first,
 * filtered from the URL.
 */
export function AuditLogScreen() {
    const {t, i18n} = useTranslation();
    const {hasRole} = useAuth();
    const isAdmin = hasRole(AppRole.DataboxAdmin) || hasRole(AppRole.Admin);
    const router = useRouter();
    const pathname = usePathname();
    const params = useSearchParams();
    const filters = useMemo(
        () => readFilters(new URLSearchParams(params.toString())),
        [params]
    );
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const setFilters = useCallback(
        (patch: Partial<ScreenFilters>) => {
            const query = writeFilters({...filters, ...patch});
            router.replace(query ? `${pathname}?${query}` : pathname, {
                scroll: false,
            });
        },
        [filters, pathname, router]
    );

    const logs = useInfiniteQuery({
        queryKey: ['audit-logs', filters],
        queryFn: ({pageParam}) =>
            getAuditLogs(toApiFilters(filters), pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        enabled: isAdmin,
    });
    const pages = logs.data?.pages ?? [];
    const items = pages.flatMap(p => p.items);

    // The impersonating admins of each page, resolved at once
    const impersonatorQueries = useQueries({
        queries: pages.map(page => {
            const ids = [
                ...new Set(
                    page.items
                        .map(l => l.impersonatorId)
                        .filter((id): id is string => !!id)
                ),
            ].sort();

            return {
                queryKey: ['users', 'ids', ids],
                queryFn: () => getUsers({ids}),
                enabled: ids.length > 0,
                staleTime: 300_000,
            };
        }),
    });
    const impersonators = useMemo(() => {
        const index: Record<string, User> = {};
        impersonatorQueries.forEach(q =>
            q.data?.forEach(u => {
                index[u.id] = u;
            })
        );

        return index;
    }, [impersonatorQueries]);

    // Where the user is, shown in the top bar with the way back to the assets
    usePageTrail(t('audit_log.title', 'Audit log'), routes.auditLog());

    const hasFilters = Object.keys(filters).length > 0;
    const objectTypeOptions = [
        {value: ALL, label: t('audit_log.filter.all_types', 'All types')},
        ...Object.keys(AUDIT_LOG_OBJECT_TYPES).map(key => ({
            value: key,
            label: auditLogObjectTypeLabel(key, t),
        })),
    ];
    const actorTypeOptions = [
        {value: ALL, label: t('audit_log.filter.all_actors', 'All actors')},
        {
            value: AuditLogActorType.User,
            label: t('audit_log.actor_type.user', 'User'),
        },
        {
            value: AuditLogActorType.OAuthClient,
            label: t('audit_log.actor_type.oauth_client', 'OAuth client'),
        },
        {
            value: AuditLogActorType.System,
            label: t('audit_log.actor_type.system', 'System'),
        },
    ];

    return (
        <RequireAuth>
            {!isAdmin ? (
                <EmptyState
                    className="h-full"
                    title={t(
                        'common.forbidden',
                        'You are not allowed to access this page'
                    )}
                />
            ) : (
                <ScrollablePage
                    data-testid="audit-log-screen"
                    className="max-w-6xl"
                >
                    <div className="flex flex-wrap items-center gap-3">
                        <ScrollTextIcon className="size-5 text-muted-foreground" />
                        <h1 className="flex-1 text-lg font-semibold">
                            {t('audit_log.title', 'Audit log')}
                        </h1>
                        {hasFilters ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    router.replace(pathname, {scroll: false})
                                }
                            >
                                <XIcon />{' '}
                                {t('audit_log.filter.clear', 'Clear filters')}
                            </Button>
                        ) : null}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => logs.refetch()}
                        >
                            <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                        </Button>
                    </div>

                    <div
                        className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        data-testid="audit-log-filters"
                    >
                        <div className="space-y-1">
                            <Label htmlFor="audit-log-action">
                                {t('audit_log.filter.action', 'Action')}
                            </Label>
                            <AsyncCombobox
                                id="audit-log-action"
                                multiple
                                queryKey={['audit-log-actions', i18n.language]}
                                loadOptions={async query =>
                                    actionOptions
                                        .map(o => ({
                                            ...o,
                                            label: auditLogActionLabel(
                                                o.value,
                                                t
                                            ),
                                        }))
                                        .filter(o =>
                                            o.label
                                                .toLowerCase()
                                                .includes(query.toLowerCase())
                                        )
                                }
                                resolveValues={async values =>
                                    values.map(v => ({
                                        value: v,
                                        label: auditLogActionLabel(v, t),
                                    }))
                                }
                                value={filters.actions ?? []}
                                onChange={actions =>
                                    setFilters({
                                        actions: actions.length
                                            ? actions
                                            : undefined,
                                    })
                                }
                                placeholder={t(
                                    'audit_log.filter.all_actions',
                                    'All actions'
                                )}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="audit-log-object-type">
                                {t(
                                    'audit_log.filter.object_type',
                                    'Object type'
                                )}
                            </Label>
                            <SimpleSelect
                                id="audit-log-object-type"
                                value={filters.objectType ?? ALL}
                                onValueChange={v =>
                                    setFilters({
                                        objectType: v === ALL ? undefined : v,
                                    })
                                }
                                options={objectTypeOptions}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="audit-log-workspace">
                                {t('audit_log.filter.workspace', 'Workspace')}
                            </Label>
                            <WorkspaceSelect
                                id="audit-log-workspace"
                                value={filters.workspaceId}
                                onChange={workspaceId =>
                                    setFilters({workspaceId})
                                }
                                placeholder={t(
                                    'audit_log.filter.all_workspaces',
                                    'All workspaces'
                                )}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="audit-log-user">
                                {t('audit_log.filter.user', 'User')}
                            </Label>
                            <UserSelect
                                id="audit-log-user"
                                value={filters.userId}
                                onChange={(userId?: string) =>
                                    setFilters({userId: userId || undefined})
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="audit-log-actor-type">
                                {t('audit_log.filter.actor_type', 'Actor')}
                            </Label>
                            <SimpleSelect
                                id="audit-log-actor-type"
                                value={filters.actorType ?? ALL}
                                onValueChange={v =>
                                    setFilters({
                                        actorType:
                                            v === ALL
                                                ? undefined
                                                : (v as AuditLogActorType),
                                    })
                                }
                                options={actorTypeOptions}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="audit-log-object-id">
                                {t('audit_log.filter.object_id', 'Object ID')}
                            </Label>
                            <Input
                                id="audit-log-object-id"
                                defaultValue={filters.objectId ?? ''}
                                key={filters.objectId ?? ''}
                                onBlur={e =>
                                    setFilters({
                                        objectId:
                                            e.target.value.trim() || undefined,
                                    })
                                }
                                onKeyDown={e => {
                                    if (e.key === 'Enter') {
                                        e.currentTarget.blur();
                                    }
                                }}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="audit-log-from">
                                {t('audit_log.filter.from', 'From')}
                            </Label>
                            <Input
                                id="audit-log-from"
                                type="date"
                                value={filters.from ?? ''}
                                onChange={e =>
                                    setFilters({
                                        from: e.target.value || undefined,
                                    })
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="audit-log-to">
                                {t('audit_log.filter.to', 'To')}
                            </Label>
                            <Input
                                id="audit-log-to"
                                type="date"
                                value={filters.to ?? ''}
                                onChange={e =>
                                    setFilters({
                                        to: e.target.value || undefined,
                                    })
                                }
                            />
                        </div>
                    </div>
                    {filters.correlationId ? (
                        <div className="flex items-center gap-2 text-sm">
                            <Badge variant="secondary">
                                <LinkIcon />
                                {t(
                                    'audit_log.filter.correlation',
                                    'Same action: {{id}}',
                                    {id: filters.correlationId.slice(0, 8)}
                                )}
                                <button
                                    type="button"
                                    className="ml-1"
                                    aria-label={t('common.remove', 'Remove')}
                                    onClick={() =>
                                        setFilters({correlationId: undefined})
                                    }
                                >
                                    <XIcon />
                                </button>
                            </Badge>
                        </div>
                    ) : null}

                    <ul className="divide-y rounded-md border bg-card">
                        {logs.isLoading
                            ? [...Array(6)].map((_, i) => (
                                  <li key={i} className="p-3">
                                      <Skeleton className="h-8" />
                                  </li>
                              ))
                            : null}
                        {items.map(log => (
                            <AuditLogRow
                                key={log.id}
                                log={log}
                                language={i18n.language}
                                impersonator={
                                    log.impersonatorId
                                        ? impersonators[log.impersonatorId]
                                        : undefined
                                }
                                expanded={!!expanded[log.id]}
                                onToggle={() =>
                                    setExpanded(e => ({
                                        ...e,
                                        [log.id]: !e[log.id],
                                    }))
                                }
                                onCorrelation={correlationId =>
                                    setFilters({correlationId})
                                }
                            />
                        ))}
                        {logs.isSuccess && items.length === 0 ? (
                            <li>
                                <EmptyState
                                    title={t(
                                        'audit_log.empty',
                                        'No entry matches'
                                    )}
                                />
                            </li>
                        ) : null}
                    </ul>
                    {logs.hasNextPage ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => logs.fetchNextPage()}
                            loading={logs.isFetchingNextPage}
                        >
                            {t('common.load_more', 'Load more')}
                        </Button>
                    ) : null}
                </ScrollablePage>
            )}
        </RequireAuth>
    );
}

function AuditLogRow({
    log,
    language,
    impersonator,
    expanded,
    onToggle,
    onCorrelation,
}: {
    log: AuditLog;
    language: string;
    impersonator?: User;
    expanded: boolean;
    onToggle: () => void;
    onCorrelation: (correlationId: string) => void;
}) {
    const {t} = useTranslation();
    const href =
        log.action !== 'object_deleted'
            ? auditLogObjectHref(log.objectType, log.objectId)
            : undefined;
    const objectLabel = log.objectLabel || log.objectId;

    return (
        <li data-testid="audit-log-row" className="text-sm">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 hover:bg-accent/40">
                <button
                    type="button"
                    data-testid="audit-log-toggle"
                    className="text-muted-foreground hover:text-foreground"
                    aria-expanded={expanded}
                    aria-label={t('audit_log.details.toggle', 'Details')}
                    onClick={onToggle}
                >
                    {expanded ? (
                        <ChevronDownIcon className="size-4" />
                    ) : (
                        <ChevronRightIcon className="size-4" />
                    )}
                </button>
                <span className="w-40 shrink-0 text-xs text-muted-foreground">
                    {formatDateTime(log.date, 'medium', language)}
                </span>
                <span className="flex min-w-0 items-center gap-1.5">
                    {log.actorType === AuditLogActorType.User ? (
                        <span className="truncate font-medium">
                            {log.user?.username ?? log.user?.id}
                        </span>
                    ) : log.actorType === AuditLogActorType.OAuthClient ? (
                        <>
                            <Badge variant="outline">
                                {t(
                                    'audit_log.actor_type.oauth_client',
                                    'OAuth client'
                                )}
                            </Badge>
                            <span className="truncate font-mono text-xs">
                                {log.clientId}
                            </span>
                        </>
                    ) : (
                        <Badge variant="muted">
                            {t('audit_log.actor_type.system', 'System')}
                        </Badge>
                    )}
                    {log.impersonatorId ? (
                        <span className="truncate text-xs text-muted-foreground">
                            {t('audit_log.via', 'via {{name}}', {
                                name: impersonator
                                    ? getUserDisplayName(impersonator)
                                    : log.impersonatorId,
                            })}
                        </span>
                    ) : null}
                </span>
                <span data-testid="audit-log-action" className="font-medium">
                    {auditLogActionLabel(log.action, t)}
                </span>
                <span className="flex min-w-0 flex-1 items-center gap-1.5">
                    {log.objectType ? (
                        <Badge variant="secondary">
                            {auditLogObjectTypeLabel(log.objectType, t)}
                        </Badge>
                    ) : null}
                    {objectLabel ? (
                        href ? (
                            <Link
                                href={href}
                                scroll={false}
                                className="truncate hover:underline"
                            >
                                {objectLabel}
                            </Link>
                        ) : (
                            <span className="truncate">{objectLabel}</span>
                        )
                    ) : null}
                </span>
                {log.correlationId ? (
                    <button
                        type="button"
                        data-testid="audit-log-correlation"
                        className="font-mono text-xs text-muted-foreground hover:text-foreground hover:underline"
                        title={t(
                            'audit_log.correlation_hint',
                            'Show the entries of the same action'
                        )}
                        onClick={() => onCorrelation(log.correlationId!)}
                    >
                        #{log.correlationId.slice(0, 8)}
                    </button>
                ) : null}
            </div>
            {expanded ? <AuditLogDetails log={log} /> : null}
        </li>
    );
}

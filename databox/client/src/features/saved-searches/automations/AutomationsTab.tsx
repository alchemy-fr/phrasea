'use client';

import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {
    FlaskConicalIcon,
    HistoryIcon,
    MoreVerticalIcon,
    PencilIcon,
    PlayIcon,
    PlusIcon,
    RotateCcwIcon,
    Trash2Icon,
    ZapIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import {AutomationScope, SavedSearch, SavedSearchAutomation} from '@/types/api';
import {
    deleteSavedSearchAutomation,
    getSavedSearchAutomations,
    patchSavedSearchAutomation,
    resetAutomationCursor,
} from '@/lib/api/automations';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {Button} from '@/components/ui/button';
import {Switch} from '@/components/ui/controls';
import {Badge, EmptyState, Skeleton} from '@/components/ui/misc';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {toastError} from '@/lib/utils/errors';
import {formatDateTime} from '@/lib/utils/format';
import {AutomationFormDialog} from './AutomationForm';
import {AutomationRunsDialog, RunStatusBadge} from './AutomationRunsPanel';
import {getActionDefinition} from './actions/registry';
import {describeSchedule} from './schedule';
import {automationsQueryKey} from './queryKeys';
import {useRunAutomation} from './useRunAutomation';

/**
 * The automations of a saved search, managed by its owner only (they act with
 * the owner's permissions).
 */
export function AutomationsTab({savedSearch}: {savedSearch: SavedSearch}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const queryClient = useQueryClient();
    const queryKey = automationsQueryKey(savedSearch.id);
    const query = useQuery({
        queryKey,
        queryFn: () => getSavedSearchAutomations(savedSearch.id),
    });
    const refresh = () => queryClient.invalidateQueries({queryKey});

    const create = async () => {
        if (
            await openModal(AutomationFormDialog, {
                savedSearchId: savedSearch.id,
            })
        ) {
            void refresh();
        }
    };

    if (query.isLoading) {
        return (
            <div className="space-y-2">
                <Skeleton className="h-14 w-full" />
                <Skeleton className="h-14 w-full" />
            </div>
        );
    }

    const automations = query.data ?? [];

    return (
        <div className="space-y-3" data-testid="automations-tab">
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground">
                    {t(
                        'automation.tab.help',
                        'Apply actions to the results of this search, on demand or on a schedule. Automations act with the permissions of the owner of the search.'
                    )}
                </p>
                <Button size="sm" onClick={create}>
                    <PlusIcon />{' '}
                    {t('automation.create.button', 'New automation')}
                </Button>
            </div>
            {automations.length === 0 ? (
                <EmptyState
                    icon={<ZapIcon />}
                    title={t('automation.tab.empty', 'No automation yet')}
                />
            ) : (
                <ul className="divide-y rounded-md border">
                    {automations.map(a => (
                        <AutomationRow
                            key={a.id}
                            automation={a}
                            savedSearchId={savedSearch.id}
                            onChange={refresh}
                        />
                    ))}
                </ul>
            )}
        </div>
    );
}

function AutomationRow({
    automation: a,
    savedSearchId,
    onChange,
}: {
    automation: SavedSearchAutomation;
    savedSearchId: string;
    onChange: () => void;
}) {
    const {t, i18n} = useTranslation();
    const {openModal} = useModals();
    const runAutomation = useRunAutomation();

    const showRuns = () => openModal(AutomationRunsDialog, {automation: a});
    const run = (dryRun: boolean) => runAutomation(a, dryRun);

    const toggle = async (enabled: boolean) => {
        try {
            await patchSavedSearchAutomation(a.id, {enabled});
            onChange();
        } catch (e) {
            toastError(e);
        }
    };

    const edit = async () => {
        if (
            await openModal(AutomationFormDialog, {
                savedSearchId,
                automation: a,
            })
        ) {
            onChange();
        }
    };

    const resetCursor = (mode: 'empty' | 'current') =>
        openModal(ConfirmDialog, {
            title:
                mode === 'empty'
                    ? t(
                          'automation.reset.empty_title',
                          'Process every result again?'
                      )
                    : t(
                          'automation.reset.current_title',
                          'Ignore the current results?'
                      ),
            description:
                mode === 'empty'
                    ? t(
                          'automation.reset.empty_help',
                          'Every current result will be considered new at the next run.'
                      )
                    : t(
                          'automation.reset.current_help',
                          'The current results will be marked as handled, without running the actions: only the assets entering the search later will be processed.'
                      ),
            onConfirm: async () => {
                await resetAutomationCursor(a.id, mode);
                toast.success(t('automation.reset.done', 'Cursor reset'));
            },
        });

    const remove = () =>
        openModal(ConfirmDialog, {
            title: t(
                'automation.delete.title',
                'Delete automation "{{name}}"?',
                {
                    name: a.name,
                }
            ),
            destructive: true,
            onConfirm: async () => {
                await deleteSavedSearchAutomation(a.id);
                onChange();
            },
        });

    const summary = a.actions
        .map(
            action =>
                getActionDefinition(action.type)?.summary(action.config, t) ??
                action.type
        )
        .join(' → ');

    return (
        <li
            className="flex items-start gap-3 p-3"
            data-testid="automation-item"
        >
            <Switch
                className="mt-0.5"
                checked={a.enabled}
                disabled={!a.capabilities.edit}
                onCheckedChange={toggle}
                aria-label={t('automation.enabled', 'Enabled')}
            />
            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="truncate text-sm font-medium">
                        {a.name}
                    </span>
                    <Badge variant="outline">
                        {a.scope === AutomationScope.New
                            ? t('automation.scope.new', 'New results only')
                            : t('automation.scope.all', 'All results')}
                    </Badge>
                    {a.lastRunStatus ? (
                        <RunStatusBadge status={a.lastRunStatus} />
                    ) : null}
                </div>
                <p className="text-xs text-muted-foreground">
                    {describeSchedule(a.schedule, t)}
                    {a.schedule ? ` (${a.timezone})` : ''}
                    {a.enabled && a.nextRunAt
                        ? ` · ${t('automation.next_run', 'next run {{date}}', {
                              date: formatDateTime(
                                  a.nextRunAt,
                                  'medium',
                                  i18n.language
                              ),
                          })}`
                        : ''}
                    {a.lastRunAt
                        ? ` · ${t('automation.last_run', 'last run {{date}}', {
                              date: formatDateTime(
                                  a.lastRunAt,
                                  'relative',
                                  i18n.language
                              ),
                          })}`
                        : ''}
                </p>
                <p className="truncate text-xs">{summary}</p>
            </div>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        aria-label={t('common.actions', 'Actions')}
                    >
                        <MoreVerticalIcon />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {a.capabilities.run ? (
                        <>
                            <DropdownMenuItem onSelect={() => run(false)}>
                                <PlayIcon />{' '}
                                {t('automation.run.now', 'Run now')}
                            </DropdownMenuItem>
                            <DropdownMenuItem onSelect={() => run(true)}>
                                <FlaskConicalIcon />{' '}
                                {t('automation.run.dry_run', 'Dry run')}
                            </DropdownMenuItem>
                        </>
                    ) : null}
                    {a.capabilities.edit ? (
                        <DropdownMenuItem onSelect={edit}>
                            <PencilIcon /> {t('common.edit', 'Edit')}
                        </DropdownMenuItem>
                    ) : null}
                    <DropdownMenuItem onSelect={showRuns}>
                        <HistoryIcon /> {t('automation.runs.menu', 'History')}
                    </DropdownMenuItem>
                    {a.capabilities.edit ? (
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>
                                <RotateCcwIcon />{' '}
                                {t('automation.reset.menu', 'Reset the cursor')}
                            </DropdownMenuSubTrigger>
                            <DropdownMenuSubContent>
                                <DropdownMenuItem
                                    onSelect={() => resetCursor('empty')}
                                >
                                    {t(
                                        'automation.reset.empty',
                                        'Process every result again'
                                    )}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => resetCursor('current')}
                                >
                                    {t(
                                        'automation.reset.current',
                                        'Ignore the current results'
                                    )}
                                </DropdownMenuItem>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                    ) : null}
                    {a.capabilities.delete ? (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={remove}
                            >
                                <Trash2Icon /> {t('common.delete', 'Delete')}
                            </DropdownMenuItem>
                        </>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>
        </li>
    );
}

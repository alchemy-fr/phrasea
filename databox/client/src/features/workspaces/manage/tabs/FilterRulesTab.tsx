'use client';

import {useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    PencilIcon,
    PlusIcon,
    SaveIcon,
    Trash2Icon,
    UsersIcon,
    UserIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {AttributeFilterRule} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {
    deleteAttributeFilterRule,
    getAttributeFilterRules,
    saveAttributeFilterRule,
} from '@/lib/api/integrations';
import {Button} from '@/components/ui/button';
import {FormRow} from '@/components/ui/input';
import {Badge, Skeleton} from '@/components/ui/misc';
import {GroupSelect, UserSelect} from '@/components/form/selects';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {ConditionDialog} from '@/features/search/conditions/ConditionDialog';
import {HumanizedExpression} from '@/features/search/conditions/SearchConditionChip';
import {SearchProvider} from '@/features/search/SearchProvider';
import {parseAQL} from '@/features/search/aql/parser';
import {toastError} from '@/lib/utils/errors';
import {
    confirmLeave,
    UnsavedChangesScope,
    useDirtyState,
    useUnsavedChangesChildScope,
} from '@/lib/navigation/unsavedChanges';
import {useRoutePath} from '@/lib/navigation/routePath';
import {useFocusFirstField} from '@/hooks/useFocusFirstField';

/**
 * Attribute filter rules: the assets of the workspace are only visible to the
 * targeted users and groups (everyone without target) when they match an AQL
 * condition.
 */
export function FilterRulesTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();

    return (
        <SearchProvider>
            <div className="space-y-8">
                <AttributeRules workspaceId={workspace.id} />
            </div>
            <span className="sr-only">
                {t('workspace.manage.filter_rules', 'Filter rules')}
            </span>
        </SearchProvider>
    );
}

function AttributeRules({workspaceId}: {workspaceId: string}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const rules = useQuery({
        queryKey: ['attribute-filter-rules', workspaceId],
        queryFn: () => getAttributeFilterRules(workspaceId),
    });
    // The rule edited is in the URL: `:ruleId` or `new`
    const {segments, navigate} = useRoutePath();
    const editingId = segments[0] ?? null;
    const editing: AttributeFilterRule | 'new' | null =
        editingId === 'new'
            ? 'new'
            : (rules.data?.items.find(r => r.id === editingId) ?? null);
    const formPane = useRef<HTMLDivElement>(null);
    useFocusFirstField(formPane, editingId === 'new');
    // The rule form: switching away from it asks first when it is dirty
    const formScope = useUnsavedChangesChildScope();
    const edit = (next: AttributeFilterRule | 'new' | null) => {
        const id = next === null || next === 'new' ? next : next.id;
        if (id === editingId) {
            return;
        }
        void confirmLeave(formScope).then(
            leave => leave && navigate(id ? [id] : [])
        );
    };

    return (
        <section className="space-y-3">
            <div className="flex items-center gap-2">
                <h3 className="text-sm font-semibold">
                    {t(
                        'filter_rules.attribute.title',
                        'Attribute filter rules'
                    )}
                </h3>
                <span className="text-xs text-muted-foreground">
                    {t(
                        'filter_rules.attribute.help',
                        'Assets are only visible to the targets when they match the condition.'
                    )}
                </span>
                <Button
                    size="sm"
                    variant="outline"
                    className="ml-auto"
                    onClick={() => edit('new')}
                >
                    <PlusIcon /> {t('filter_rules.add', 'Add rule')}
                </Button>
            </div>
            {rules.isLoading ? <Skeleton className="h-16" /> : null}
            <div className="space-y-2">
                {rules.data?.items.map(rule => (
                    <div
                        key={rule.id}
                        data-testid="filter-rule"
                        className="flex items-start gap-3 rounded-md border p-3 text-sm"
                    >
                        <div className="min-w-0 flex-1 space-y-1">
                            <RuleTargets rule={rule} />
                            <RuleCondition condition={rule.condition} />
                        </div>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            onClick={() => edit(rule)}
                            aria-label={t('common.edit', 'Edit')}
                        >
                            <PencilIcon />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            className="text-destructive"
                            aria-label={t('common.delete', 'Delete')}
                            onClick={() =>
                                openModal(ConfirmDialog, {
                                    title: t(
                                        'filter_rules.delete',
                                        'Delete this rule?'
                                    ),
                                    destructive: true,
                                    onConfirm: async () => {
                                        await deleteAttributeFilterRule(
                                            rule.id
                                        );
                                        if (rule.id === editingId) {
                                            navigate([], {replace: true});
                                        }
                                        void rules.refetch();
                                    },
                                })
                            }
                        >
                            <Trash2Icon />
                        </Button>
                    </div>
                ))}
                {rules.data?.items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('filter_rules.none', 'No rule')}
                    </p>
                ) : null}
            </div>
            {editing ? (
                <UnsavedChangesScope.Provider value={formScope}>
                    <div ref={formPane}>
                        <AttributeRuleForm
                            key={editing === 'new' ? 'new' : editing.id}
                            rule={editing === 'new' ? undefined : editing}
                            workspaceId={workspaceId}
                            onSaved={() => {
                                navigate([], {replace: true});
                                void rules.refetch();
                            }}
                            onCancel={() => edit(null)}
                        />
                    </div>
                </UnsavedChangesScope.Provider>
            ) : null}
        </section>
    );
}

function RuleTargets({rule}: {rule: AttributeFilterRule}) {
    const {t} = useTranslation();
    if (!rule.users.length && !rule.groups.length) {
        return (
            <Badge variant="secondary">
                {t('filter_rules.everyone', 'Everyone')}
            </Badge>
        );
    }

    return (
        <div className="flex flex-wrap gap-1">
            {rule.users.map(u => (
                <Badge key={u.id} variant="outline">
                    <UserIcon /> {u.name}
                </Badge>
            ))}
            {rule.groups.map(g => (
                <Badge key={g.id} variant="outline">
                    <UsersIcon /> {g.name}
                </Badge>
            ))}
        </div>
    );
}

function RuleCondition({condition}: {condition: string}) {
    // An unparsable condition is displayed as written
    const ast = parseAQL(condition);

    return (
        <div
            data-testid="filter-rule-condition"
            className="rounded bg-muted/60 px-2 py-1 font-mono text-xs"
        >
            {ast ? (
                <HumanizedExpression expression={ast.expression} />
            ) : (
                condition
            )}
        </div>
    );
}

function AttributeRuleForm({
    rule,
    workspaceId,
    onSaved,
    onCancel,
}: {
    rule?: AttributeFilterRule;
    workspaceId: string;
    onSaved: () => void;
    onCancel: () => void;
}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [userIds, setUserIds] = useState<string[]>(
        (rule?.users ?? []).map(u => u.id)
    );
    const [groupIds, setGroupIds] = useState<string[]>(
        (rule?.groups ?? []).map(g => g.id)
    );
    const [condition, setCondition] = useState(rule?.condition ?? '');
    const [saving, setSaving] = useState(false);
    useDirtyState({userIds, groupIds, condition});

    const save = async () => {
        setSaving(true);
        try {
            await saveAttributeFilterRule({
                id: rule?.id,
                userIds,
                groupIds,
                workspaceId,
                condition,
            });
            toast.success(t('filter_rules.saved', 'Rule saved'));
            onSaved();
        } catch (e: unknown) {
            toastError(e);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-3 rounded-md border border-dashed p-3">
            <div className="grid gap-3 sm:grid-cols-2">
                <FormRow
                    label={t('filter_rules.users', 'Users')}
                    help={t('asset_policy.targets_help', 'Empty = everyone')}
                >
                    <UserSelect
                        multiple
                        value={userIds}
                        onChange={setUserIds}
                    />
                </FormRow>
                <FormRow label={t('filter_rules.groups', 'Groups')}>
                    <GroupSelect
                        multiple
                        value={groupIds}
                        onChange={setGroupIds}
                    />
                </FormRow>
            </div>
            <FormRow label={t('filter_rules.condition', 'Condition')}>
                <div className="flex items-center gap-2">
                    <code
                        data-testid="filter-rule-form-condition"
                        className="min-w-0 flex-1 truncate rounded bg-muted px-2 py-1.5 text-xs"
                    >
                        {condition ||
                            t('filter_rules.no_condition', 'No condition yet')}
                    </code>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            openModal(ConditionDialog, {
                                condition: condition
                                    ? {id: 'rule', query: condition}
                                    : undefined,
                                onSubmit: setCondition,
                                workspaceId,
                                title: t('filter_rules.condition', 'Condition'),
                            })
                        }
                    >
                        <PencilIcon />{' '}
                        {condition
                            ? t('filter_rules.edit_condition', 'Edit condition')
                            : t('filter_rules.add_condition', 'Add condition')}
                    </Button>
                </div>
            </FormRow>
            <div className="flex justify-end gap-2">
                <Button variant="ghost" size="sm" onClick={onCancel}>
                    {t('common.cancel', 'Cancel')}
                </Button>
                <Button
                    size="sm"
                    onClick={save}
                    loading={saving}
                    disabled={!condition}
                >
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

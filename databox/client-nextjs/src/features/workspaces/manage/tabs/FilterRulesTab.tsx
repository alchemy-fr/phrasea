'use client';

import {useState} from 'react';
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
import type {
    AttributeFilterRule,
    Group,
    TagFilterRule,
    User,
} from '@/types/api';
import {EntityName} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {
    deleteAttributeFilterRule,
    deleteTagFilterRule,
    getAttributeFilterRules,
    getTagFilterRules,
    saveAttributeFilterRule,
    saveTagFilterRule,
} from '@/lib/api/integrations';
import {Button} from '@/components/ui/button';
import {FormRow} from '@/components/ui/input';
import {Badge, Skeleton} from '@/components/ui/misc';
import {GroupSelect, TagSelect, UserSelect} from '@/components/form/selects';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {ConditionDialog} from '@/features/search/conditions/ConditionDialog';
import {SearchProvider} from '@/features/search/SearchProvider';
import {HumanizedExpression} from '@/features/search/conditions/SearchConditionChip';
import {parseAQL} from '@/features/search/aql/parser';
import {iri, idFromIri} from '@/lib/utils/iri';
import {TagChip} from '@/components/chips';

/**
 * Attribute filter rules (AQL condition per users / groups) and tag filter
 * rules (include / exclude tags).
 */
export function FilterRulesTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();

    return (
        <SearchProvider>
            <div className="space-y-8">
                <AttributeRules workspaceId={workspace.id} />
                <TagRules workspaceId={workspace.id} />
            </div>
            <span className="sr-only">
                {t('workspace.manage.filter_rules', 'Filter rules')}
            </span>
        </SearchProvider>
    );
}

function idOf(x: User | Group | string): string {
    return typeof x === 'string' ? idFromIri(x) : x.id;
}

function AttributeRules({workspaceId}: {workspaceId: string}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const rules = useQuery({
        queryKey: ['attribute-filter-rules', workspaceId],
        queryFn: () => getAttributeFilterRules(workspaceId),
    });
    const [editing, setEditing] = useState<AttributeFilterRule | 'new' | null>(
        null
    );

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
                    onClick={() => setEditing('new')}
                >
                    <PlusIcon /> {t('filter_rules.add', 'Add rule')}
                </Button>
            </div>
            {rules.isLoading ? <Skeleton className="h-16" /> : null}
            <div className="space-y-2">
                {rules.data?.items.map(rule => (
                    <div
                        key={rule.id}
                        className="flex items-start gap-3 rounded-md border p-3 text-sm"
                    >
                        <div className="min-w-0 flex-1 space-y-1">
                            <RuleTargets
                                users={rule.users}
                                groups={rule.groups}
                            />
                            <div className="rounded bg-muted/60 px-2 py-1 font-mono text-xs">
                                {parseAQL(rule.condition) ? (
                                    <HumanizedExpression
                                        expression={
                                            parseAQL(rule.condition)!.expression
                                        }
                                    />
                                ) : (
                                    rule.condition
                                )}
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            onClick={() => setEditing(rule)}
                        >
                            <PencilIcon />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            className="text-destructive"
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
                                        void rules.refetch();
                                    },
                                })
                            }
                        >
                            <Trash2Icon />
                        </Button>
                    </div>
                ))}
                {rules.data?.items.length === 0 && !editing ? (
                    <p className="text-sm text-muted-foreground">
                        {t('filter_rules.none', 'No rule')}
                    </p>
                ) : null}
            </div>
            {editing ? (
                <AttributeRuleForm
                    key={editing === 'new' ? 'new' : editing.id}
                    rule={editing === 'new' ? undefined : editing}
                    workspaceId={workspaceId}
                    onSaved={() => {
                        setEditing(null);
                        void rules.refetch();
                    }}
                    onCancel={() => setEditing(null)}
                />
            ) : null}
        </section>
    );
}

function RuleTargets({
    users,
    groups,
}: {
    users?: (User | string)[];
    groups?: (Group | string)[];
}) {
    const {t} = useTranslation();
    if (!users?.length && !groups?.length) {
        return (
            <Badge variant="secondary">
                {t('filter_rules.everyone', 'Everyone')}
            </Badge>
        );
    }

    return (
        <div className="flex flex-wrap gap-1">
            {users?.map(u => (
                <Badge key={idOf(u)} variant="outline">
                    <UserIcon /> {typeof u === 'string' ? idOf(u) : u.username}
                </Badge>
            ))}
            {groups?.map(g => (
                <Badge key={idOf(g)} variant="outline">
                    <UsersIcon /> {typeof g === 'string' ? idOf(g) : g.name}
                </Badge>
            ))}
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
    const [users, setUsers] = useState<string[]>((rule?.users ?? []).map(idOf));
    const [groups, setGroups] = useState<string[]>(
        (rule?.groups ?? []).map(idOf)
    );
    const [condition, setCondition] = useState(rule?.condition ?? '');
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            await saveAttributeFilterRule({
                id: rule?.id,
                users,
                groups,
                condition,
                workspace: iri(EntityName.Workspace, workspaceId),
            });
            toast.success(t('filter_rules.saved', 'Rule saved'));
            onSaved();
        } catch (e: any) {
            toast.error(e?.message);
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
                    <UserSelect multiple value={users} onChange={setUsers} />
                </FormRow>
                <FormRow label={t('filter_rules.groups', 'Groups')}>
                    <GroupSelect multiple value={groups} onChange={setGroups} />
                </FormRow>
            </div>
            <FormRow label={t('filter_rules.condition', 'Condition')}>
                <div className="flex items-center gap-2">
                    <code className="min-w-0 flex-1 truncate rounded bg-muted px-2 py-1.5 text-xs">
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
                            ? t('common.edit', 'Edit')
                            : t('common.add', 'Add')}
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
                    disabled={!condition}
                    loading={saving}
                >
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

function TagRules({workspaceId}: {workspaceId: string}) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const rules = useQuery({
        queryKey: ['tag-filter-rules', workspaceId],
        queryFn: () => getTagFilterRules({workspaceId}),
    });
    const [editing, setEditing] = useState<TagFilterRule | 'new' | null>(null);

    return (
        <section className="space-y-3">
            <div className="flex items-center gap-2">
                <h3 className="text-sm font-semibold">
                    {t('filter_rules.tag.title', 'Tag filter rules')}
                </h3>
                <span className="text-xs text-muted-foreground">
                    {t(
                        'filter_rules.tag.help',
                        'Restrict visible assets by tags for a user or a group.'
                    )}
                </span>
                <Button
                    size="sm"
                    variant="outline"
                    className="ml-auto"
                    onClick={() => setEditing('new')}
                >
                    <PlusIcon /> {t('filter_rules.add', 'Add rule')}
                </Button>
            </div>
            {rules.isLoading ? <Skeleton className="h-16" /> : null}
            <div className="space-y-2">
                {rules.data?.items.map(rule => (
                    <div
                        key={rule.id}
                        className="flex items-start gap-3 rounded-md border p-3 text-sm"
                    >
                        <div className="min-w-0 flex-1 space-y-1">
                            <div className="flex flex-wrap gap-1">
                                {rule.userId ? (
                                    <Badge variant="outline">
                                        <UserIcon />{' '}
                                        {rule.username ?? rule.userId}
                                    </Badge>
                                ) : rule.groupId ? (
                                    <Badge variant="outline">
                                        <UsersIcon />{' '}
                                        {rule.groupName ?? rule.groupId}
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary">
                                        {t('filter_rules.everyone', 'Everyone')}
                                    </Badge>
                                )}
                            </div>
                            <div className="flex flex-wrap items-center gap-1 text-xs">
                                {rule.include.length > 0 ? (
                                    <span className="text-muted-foreground">
                                        {t('filter_rules.include', 'Include')}:
                                    </span>
                                ) : null}
                                {rule.include.map(tag => (
                                    <TagChip key={tag.id} tag={tag} />
                                ))}
                                {rule.exclude.length > 0 ? (
                                    <span className="ml-2 text-muted-foreground">
                                        {t('filter_rules.exclude', 'Exclude')}:
                                    </span>
                                ) : null}
                                {rule.exclude.map(tag => (
                                    <TagChip key={tag.id} tag={tag} />
                                ))}
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            onClick={() => setEditing(rule)}
                        >
                            <PencilIcon />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            className="text-destructive"
                            onClick={() =>
                                openModal(ConfirmDialog, {
                                    title: t(
                                        'filter_rules.delete',
                                        'Delete this rule?'
                                    ),
                                    destructive: true,
                                    onConfirm: async () => {
                                        await deleteTagFilterRule(rule.id);
                                        void rules.refetch();
                                    },
                                })
                            }
                        >
                            <Trash2Icon />
                        </Button>
                    </div>
                ))}
            </div>
            {editing ? (
                <TagRuleForm
                    key={editing === 'new' ? 'new' : editing.id}
                    rule={editing === 'new' ? undefined : editing}
                    workspaceId={workspaceId}
                    onSaved={() => {
                        setEditing(null);
                        void rules.refetch();
                    }}
                    onCancel={() => setEditing(null)}
                />
            ) : null}
        </section>
    );
}

function TagRuleForm({
    rule,
    workspaceId,
    onSaved,
    onCancel,
}: {
    rule?: TagFilterRule;
    workspaceId: string;
    onSaved: () => void;
    onCancel: () => void;
}) {
    const {t} = useTranslation();
    const [userId, setUserId] = useState<string | undefined>(rule?.userId);
    const [groupId, setGroupId] = useState<string | undefined>(rule?.groupId);
    const [include, setInclude] = useState<string[]>(
        (rule?.include ?? []).map(tg => tg.id)
    );
    const [exclude, setExclude] = useState<string[]>(
        (rule?.exclude ?? []).map(tg => tg.id)
    );
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            await saveTagFilterRule({
                id: rule?.id,
                userId: userId || undefined,
                groupId: groupId || undefined,
                workspaceId,
                include: include.map(id => iri(EntityName.Tag, id)),
                exclude: exclude.map(id => iri(EntityName.Tag, id)),
            });
            toast.success(t('filter_rules.saved', 'Rule saved'));
            onSaved();
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-3 rounded-md border border-dashed p-3">
            <div className="grid gap-3 sm:grid-cols-2">
                <FormRow label={t('acl.user', 'User')}>
                    <UserSelect
                        value={userId}
                        onChange={v => {
                            setUserId(v);
                            if (v) setGroupId(undefined);
                        }}
                    />
                </FormRow>
                <FormRow label={t('acl.group', 'Group')}>
                    <GroupSelect
                        value={groupId}
                        onChange={v => {
                            setGroupId(v);
                            if (v) setUserId(undefined);
                        }}
                    />
                </FormRow>
                <FormRow label={t('filter_rules.include', 'Include')}>
                    <TagSelect
                        multiple
                        workspaceId={workspaceId}
                        value={include}
                        onChange={setInclude}
                    />
                </FormRow>
                <FormRow label={t('filter_rules.exclude', 'Exclude')}>
                    <TagSelect
                        multiple
                        workspaceId={workspaceId}
                        value={exclude}
                        onChange={setExclude}
                    />
                </FormRow>
            </div>
            <div className="flex justify-end gap-2">
                <Button variant="ghost" size="sm" onClick={onCancel}>
                    {t('common.cancel', 'Cancel')}
                </Button>
                <Button size="sm" onClick={save} loading={saving}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

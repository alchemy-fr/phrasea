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
import type {TagFilterRule} from '@/types/api';
import {EntityName} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {
    deleteTagFilterRule,
    getTagFilterRules,
    saveTagFilterRule,
} from '@/lib/api/integrations';
import {Button} from '@/components/ui/button';
import {FormRow} from '@/components/ui/input';
import {Badge, Skeleton} from '@/components/ui/misc';
import {GroupSelect, TagSelect, UserSelect} from '@/components/form/selects';
import {useModals} from '@/components/modals/ModalProvider';
import {ConfirmDialog} from '@/components/ui/confirm';
import {iri} from '@/lib/utils/iri';
import {TagChip} from '@/components/chips';
import {
    confirmLeave,
    UnsavedChangesScope,
    useDirtyState,
    useUnsavedChangesChildScope,
} from '@/lib/navigation/unsavedChanges';

/**
 * Tag filter rules: tags included / excluded for users or groups.
 */
export function FilterRulesTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();

    return (
        <>
            <div className="space-y-8">
                <TagRules workspaceId={workspace.id} />
            </div>
            <span className="sr-only">
                {t('workspace.manage.filter_rules', 'Filter rules')}
            </span>
        </>
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
    // The rule form: switching away from it asks first when it is dirty
    const formScope = useUnsavedChangesChildScope();
    const edit = (next: TagFilterRule | 'new' | null) => {
        const keyOf = (e: typeof next) =>
            e === null || e === 'new' ? e : e.id;
        if (keyOf(next) === keyOf(editing)) {
            return;
        }
        void confirmLeave(formScope).then(leave => leave && setEditing(next));
    };

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
                            onClick={() => edit(rule)}
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
                {rules.data?.items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('filter_rules.none', 'No rule')}
                    </p>
                ) : null}
            </div>
            {editing ? (
                <UnsavedChangesScope.Provider value={formScope}>
                    <TagRuleForm
                        key={editing === 'new' ? 'new' : editing.id}
                        rule={editing === 'new' ? undefined : editing}
                        workspaceId={workspaceId}
                        onSaved={() => {
                            setEditing(null);
                            void rules.refetch();
                        }}
                        onCancel={() => edit(null)}
                    />
                </UnsavedChangesScope.Provider>
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
    useDirtyState({
        userId: userId || undefined,
        groupId: groupId || undefined,
        include,
        exclude,
    });

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
                <FormRow label={t('acl.group', 'Group')}>
                    <GroupSelect
                        value={groupId}
                        onChange={v => {
                            setGroupId(v);
                            if (v) setUserId(undefined);
                        }}
                    />
                </FormRow>
                <FormRow label={t('acl.user', 'User')}>
                    <UserSelect
                        value={userId}
                        onChange={v => {
                            setUserId(v);
                            if (v) setGroupId(undefined);
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
                <Button
                    size="sm"
                    onClick={save}
                    loading={saving}
                    // The API requires a user or a group target
                    disabled={!userId && !groupId}
                >
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

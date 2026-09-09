'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {PlusIcon, SaveIcon, XIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {api} from '@/lib/api/http';
import {toPage} from '@/lib/api/hydra';
import {
    EntityName,
    type HydraCollection,
    type User,
    type Group,
} from '@/types/api';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {Badge} from '@/components/ui/misc';
import {GroupSelect, UserSelect} from '@/components/form/selects';
import {iri, toIris} from '@/lib/utils/iri';

type AssetPolicyCondition = {field?: string; operator: string; value: string};
type AssetPolicyAction = {
    action: 'hide_rendition' | 'hide_attribute';
    [k: string]: unknown;
};
type AssetPolicy = {
    'id': string;
    '@id': string;
    'name': string;
    'enabled': boolean;
    'conditions': AssetPolicyCondition[];
    'actions': AssetPolicyAction[];
    'users': (User | string)[];
    'groups': (Group | string)[];
};

const endpoint = `/${EntityName.AssetPolicy}`;

export function AssetPoliciesTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const policies = useQuery({
        queryKey: ['asset-policies', workspace.id],
        queryFn: async () =>
            toPage(
                await api.get<HydraCollection<AssetPolicy>>(endpoint, {
                    params: {workspaceId: workspace.id},
                })
            ),
    });

    return (
        <DefinitionManager<AssetPolicy>
            items={policies.data?.items}
            loading={policies.isLoading}
            onChanged={() => policies.refetch()}
            filter={(p, q) => p.name.toLowerCase().includes(q)}
            renderItem={p => (
                <span className="flex items-center gap-2">
                    <span className="flex-1 truncate">{p.name}</span>
                    {!p.enabled ? (
                        <Badge variant="destructive">
                            {t('common.disabled', 'disabled')}
                        </Badge>
                    ) : null}
                    <Badge variant="muted">
                        {t(
                            'asset_policy.actions_count',
                            '{{count}} action(s)',
                            {count: p.actions?.length ?? 0}
                        )}
                    </Badge>
                </span>
            )}
            onDelete={p => api.delete(`${endpoint}/${p.id}`)}
            createLabel={t('policy.create', 'New policy')}
            renderForm={(p, onSaved) => (
                <PolicyForm
                    key={p?.id ?? 'new'}
                    policy={p}
                    workspaceId={workspace.id}
                    onSaved={onSaved}
                />
            )}
        />
    );
}

function PolicyForm({
    policy,
    workspaceId,
    onSaved,
}: {
    policy?: AssetPolicy;
    workspaceId: string;
    onSaved: (p: AssetPolicy) => void;
}) {
    const {t} = useTranslation();
    const idOf = (x: User | Group | string) =>
        typeof x === 'string' ? x.split('/').pop()! : x.id;
    const [name, setName] = useState(policy?.name ?? '');
    const [enabled, setEnabled] = useState(policy?.enabled ?? true);
    const [users, setUsers] = useState<string[]>(
        (policy?.users ?? []).map(idOf)
    );
    const [groups, setGroups] = useState<string[]>(
        (policy?.groups ?? []).map(idOf)
    );
    const [conditions, setConditions] = useState<AssetPolicyCondition[]>(
        policy?.conditions ?? []
    );
    const [actions, setActions] = useState<AssetPolicyAction[]>(
        policy?.actions ?? []
    );
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            const data = {
                name,
                enabled,
                users,
                groups,
                conditions,
                actions,
                workspace: iri(EntityName.Workspace, workspaceId),
            };
            const saved = policy
                ? await api.put<AssetPolicy>(
                      `${endpoint}/${policy.id}`,
                      toIris(data)
                  )
                : await api.post<AssetPolicy>(endpoint, data);
            toast.success(t('policy.saved', 'Policy saved'));
            onSaved(saved);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-4">
            <FormRow label={t('common.name', 'Name')}>
                <Input value={name} onChange={e => setName(e.target.value)} />
            </FormRow>
            <LabeledControl label={t('common.enabled', 'Enabled')}>
                <Switch checked={enabled} onCheckedChange={setEnabled} />
            </LabeledControl>
            <div className="grid gap-3 sm:grid-cols-2">
                <FormRow
                    label={t('asset_policy.users', 'Target users')}
                    help={t('asset_policy.targets_help', 'Empty = everyone')}
                >
                    <UserSelect multiple value={users} onChange={setUsers} />
                </FormRow>
                <FormRow label={t('asset_policy.groups', 'Target groups')}>
                    <GroupSelect multiple value={groups} onChange={setGroups} />
                </FormRow>
            </div>
            <FormRow
                label={t(
                    'asset_policy.conditions',
                    'Conditions (all must match)'
                )}
            >
                <div className="space-y-2">
                    {conditions.map((c, i) => (
                        <div key={i} className="flex items-center gap-2">
                            <Input
                                className="w-40 font-mono"
                                placeholder="field"
                                value={c.field ?? ''}
                                onChange={e =>
                                    setConditions(
                                        conditions.map((x, j) =>
                                            j === i
                                                ? {...x, field: e.target.value}
                                                : x
                                        )
                                    )
                                }
                            />
                            <SimpleSelect
                                className="w-20"
                                value={c.operator}
                                onValueChange={v =>
                                    setConditions(
                                        conditions.map((x, j) =>
                                            j === i ? {...x, operator: v} : x
                                        )
                                    )
                                }
                                options={[
                                    {value: '=', label: '='},
                                    {value: '!=', label: '!='},
                                ]}
                            />
                            <Input
                                className="flex-1"
                                placeholder="value"
                                value={String(c.value ?? '')}
                                onChange={e =>
                                    setConditions(
                                        conditions.map((x, j) =>
                                            j === i
                                                ? {...x, value: e.target.value}
                                                : x
                                        )
                                    )
                                }
                            />
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={() =>
                                    setConditions(
                                        conditions.filter((_, j) => j !== i)
                                    )
                                }
                            >
                                <XIcon />
                            </Button>
                        </div>
                    ))}
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setConditions([
                                ...conditions,
                                {field: '', operator: '=', value: ''},
                            ])
                        }
                    >
                        <PlusIcon />{' '}
                        {t('asset_policy.add_condition', 'Add condition')}
                    </Button>
                </div>
            </FormRow>
            <FormRow label={t('asset_policy.actions', 'Actions')}>
                <div className="space-y-2">
                    {actions.map((a, i) => (
                        <div key={i} className="flex items-center gap-2">
                            <SimpleSelect
                                className="w-44"
                                value={a.action}
                                onValueChange={v =>
                                    setActions(
                                        actions.map((x, j) =>
                                            j === i
                                                ? {
                                                      ...x,
                                                      action: v as AssetPolicyAction['action'],
                                                  }
                                                : x
                                        )
                                    )
                                }
                                options={[
                                    {
                                        value: 'hide_rendition',
                                        label: t(
                                            'asset_policy.hide_rendition',
                                            'Hide rendition'
                                        ),
                                    },
                                    {
                                        value: 'hide_attribute',
                                        label: t(
                                            'asset_policy.hide_attribute',
                                            'Hide attribute'
                                        ),
                                    },
                                ]}
                            />
                            <Input
                                className="flex-1 font-mono"
                                placeholder={
                                    a.action === 'hide_rendition'
                                        ? 'rendition name'
                                        : 'attribute slug'
                                }
                                value={String(
                                    (a.action === 'hide_rendition'
                                        ? a.rendition
                                        : a.attribute) ?? ''
                                )}
                                onChange={e =>
                                    setActions(
                                        actions.map((x, j) =>
                                            j === i
                                                ? {
                                                      action: x.action,
                                                      [x.action ===
                                                      'hide_rendition'
                                                          ? 'rendition'
                                                          : 'attribute']:
                                                          e.target.value,
                                                  }
                                                : x
                                        )
                                    )
                                }
                            />
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={() =>
                                    setActions(
                                        actions.filter((_, j) => j !== i)
                                    )
                                }
                            >
                                <XIcon />
                            </Button>
                        </div>
                    ))}
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setActions([...actions, {action: 'hide_rendition'}])
                        }
                    >
                        <PlusIcon />{' '}
                        {t('asset_policy.add_action', 'Add action')}
                    </Button>
                </div>
            </FormRow>
            <div className="flex justify-end">
                <Button onClick={save} loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

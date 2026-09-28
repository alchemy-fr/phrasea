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
    type AttributeDefinition,
    type HydraCollection,
    type Page,
    type RenditionDefinition,
    type User,
    type Group,
} from '@/types/api';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {Badge} from '@/components/ui/misc';
import {GroupSelect, UserSelect} from '@/components/form/selects';
import {CollectionTreePicker} from '@/components/form/CollectionTreePicker';
import {AsyncCombobox, ComboOption} from '@/components/form/AsyncCombobox';
import {getRenditionDefinitions} from '@/lib/api/misc';
import {getCollection} from '@/lib/api/collections';
import {getWorkspaceAttributeDefinitions} from '@/lib/api/metadata';
import {iri, toIris} from '@/lib/utils/iri';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';

type AssetPolicyCondition = {field?: string; operator: string; value: string};
const HIDE_RENDITION = 'hide_rendition';
const HIDE_ATTRIBUTE = 'hide_attribute';
type ActionName = typeof HIDE_RENDITION | typeof HIDE_ATTRIBUTE;
/** What `AssetPolicyManager` reads: the id of the definition to hide */
type AssetPolicyAction = {
    action: ActionName;
    definitionId?: string;
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
    const renditions = useQuery({
        queryKey: ['rendition-definitions', 'manage', workspaceId],
        queryFn: () => getRenditionDefinitions({workspaceIds: [workspaceId]}),
    });
    const attributes = useQuery({
        queryKey: ['attribute-definitions', 'manage', workspaceId],
        queryFn: () => getWorkspaceAttributeDefinitions({workspaceId}),
    });
    const [actions, setActions] = useState<AssetPolicyAction[]>(
        policy?.actions ?? []
    );
    const hidden = (action: ActionName) =>
        actions
            .filter(a => a.action === action)
            .map(a => definitionIdOf(a, renditions.data, attributes.data))
            .filter((id): id is string => !!id);
    const setHidden = (action: ActionName, ids: string[]) =>
        setActions([
            ...actions.filter(a => a.action !== action),
            ...ids.map(definitionId => ({action, definitionId})),
        ]);
    const [saving, setSaving] = useState(false);
    const {markSaved} = useDirtyState({
        name,
        enabled,
        users,
        groups,
        conditions,
        actions,
    });

    const save = async () => {
        setSaving(true);
        try {
            const data = {
                name,
                enabled,
                users,
                groups,
                conditions,
                actions: (
                    [HIDE_RENDITION, HIDE_ATTRIBUTE] as ActionName[]
                ).flatMap(action =>
                    hidden(action).map(definitionId => ({action, definitionId}))
                ),
                workspace: iri(EntityName.Workspace, workspaceId),
            };
            const saved = policy
                ? await api.put<AssetPolicy>(
                      `${endpoint}/${policy.id}`,
                      toIris(data)
                  )
                : await api.post<AssetPolicy>(endpoint, data);
            toast.success(t('policy.saved', 'Policy saved'));
            markSaved();
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
                    label={t('asset_policy.groups', 'Target groups')}
                    help={t('asset_policy.targets_help', 'Empty = everyone')}
                >
                    <GroupSelect multiple value={groups} onChange={setGroups} />
                </FormRow>
                <FormRow label={t('asset_policy.users', 'Target users')}>
                    <UserSelect multiple value={users} onChange={setUsers} />
                </FormRow>
            </div>
            <FormRow
                label={t(
                    'asset_policy.conditions',
                    'Conditions (all must match)'
                )}
            >
                <PolicyConditions
                    conditions={conditions}
                    onChange={setConditions}
                    workspaceId={workspaceId}
                />
            </FormRow>
            <div className="grid gap-3 sm:grid-cols-2">
                <FormRow
                    label={t(
                        'asset_policy.hidden_renditions',
                        'Hide renditions'
                    )}
                    htmlFor="asset-policy-renditions"
                >
                    <DefinitionMultiSelect
                        id="asset-policy-renditions"
                        options={(renditions.data?.items ?? []).map(r => ({
                            value: r.id,
                            label: r.displayName ?? r.name,
                        }))}
                        value={hidden(HIDE_RENDITION)}
                        onChange={ids => setHidden(HIDE_RENDITION, ids)}
                    />
                </FormRow>
                <FormRow
                    label={t(
                        'asset_policy.hidden_attributes',
                        'Hide attributes'
                    )}
                    htmlFor="asset-policy-attributes"
                >
                    <DefinitionMultiSelect
                        id="asset-policy-attributes"
                        options={(attributes.data?.items ?? []).map(d => ({
                            value: d.id,
                            label: d.displayName ?? d.name,
                        }))}
                        value={hidden(HIDE_ATTRIBUTE)}
                        onChange={ids => setHidden(HIDE_ATTRIBUTE, ids)}
                    />
                </FormRow>
            </div>
            <div className="flex justify-end">
                <Button onClick={save} loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

/**
 * Early versions of this screen stored the rendition name / attribute slug
 * (`rendition` / `attribute`) instead of the `definitionId` the API reads.
 */
function definitionIdOf(
    a: AssetPolicyAction,
    renditions?: Page<RenditionDefinition>,
    attributes?: Page<AttributeDefinition>
): string | undefined {
    if (a.definitionId) {
        return a.definitionId;
    }
    if (a.action === HIDE_RENDITION && typeof a.rendition === 'string') {
        return renditions?.items.find(r => r.name === a.rendition)?.id;
    }
    if (a.action === HIDE_ATTRIBUTE && typeof a.attribute === 'string') {
        return attributes?.items.find(d => d.slug === a.attribute)?.id;
    }

    return undefined;
}

function DefinitionMultiSelect({
    id,
    options,
    value,
    onChange,
}: {
    id: string;
    options: ComboOption[];
    value: string[];
    onChange: (ids: string[]) => void;
}) {
    return (
        <AsyncCombobox
            id={id}
            multiple
            queryKey={['asset-policy-definitions', id, options]}
            loadOptions={async query =>
                options.filter(o =>
                    o.label.toLowerCase().includes(query.toLowerCase())
                )
            }
            resolveValue={async v => options.find(o => o.value === v)}
            value={value}
            onChange={onChange}
        />
    );
}

/** The only condition `AssetPolicyManager::matchesConditions` evaluates */
const COLLECTION = 'collection';

/**
 * Conditions of a policy, laid out as the search condition builder (field,
 * operator, value), restricted to what the API evaluates: the asset's
 * reference collection being a given collection or below it.
 */
function PolicyConditions({
    conditions,
    onChange,
    workspaceId,
}: {
    conditions: AssetPolicyCondition[];
    onChange: (conditions: AssetPolicyCondition[]) => void;
    workspaceId: string;
}) {
    const {t} = useTranslation();
    const update = (i: number, c: AssetPolicyCondition) =>
        onChange(conditions.map((x, j) => (j === i ? c : x)));

    return (
        <div className="space-y-2">
            {conditions.map((c, i) => (
                <div
                    key={i}
                    className="flex flex-wrap items-start gap-2 rounded-md bg-muted/40 p-2"
                    data-testid="asset-policy-condition"
                >
                    <SimpleSelect
                        size="sm"
                        className="w-44"
                        value={c.field}
                        onValueChange={field =>
                            update(i, {field, operator: '=', value: ''})
                        }
                        options={[
                            {
                                value: COLLECTION,
                                label: t(
                                    'asset_policy.field.collection',
                                    'Collection'
                                ),
                            },
                            // Saved by hand, not evaluated: shown as is
                            ...(c.field && c.field !== COLLECTION
                                ? [{value: c.field, label: c.field}]
                                : []),
                        ]}
                    />
                    <SimpleSelect
                        size="sm"
                        className="w-44"
                        value={c.operator}
                        onValueChange={operator => update(i, {...c, operator})}
                        options={[
                            {
                                value: '=',
                                label: t(
                                    'asset_policy.operator.in',
                                    'is in (or below)'
                                ),
                            },
                        ]}
                    />
                    <div className="min-w-56 flex-1">
                        {c.field === COLLECTION ? (
                            <>
                                <SelectedCollection id={c.value} />
                                <CollectionTreePicker
                                    workspaceId={workspaceId}
                                    allowWorkspace={false}
                                    className="max-h-48 bg-background"
                                    value={
                                        c.value
                                            ? {
                                                  iri: iri(
                                                      EntityName.Collection,
                                                      c.value
                                                  ),
                                                  workspaceId,
                                                  collectionId: c.value,
                                                  label: '',
                                              }
                                            : undefined
                                    }
                                    onChange={selection =>
                                        update(i, {
                                            ...c,
                                            value:
                                                selection?.collectionId ?? '',
                                        })
                                    }
                                />
                            </>
                        ) : (
                            <Input
                                className="h-8"
                                value={String(c.value ?? '')}
                                onChange={e =>
                                    update(i, {...c, value: e.target.value})
                                }
                            />
                        )}
                    </div>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={() =>
                            onChange(conditions.filter((_, j) => j !== i))
                        }
                        aria-label={t('common.remove', 'Remove')}
                    >
                        <XIcon />
                    </Button>
                </div>
            ))}
            <Button
                variant="outline"
                size="sm"
                onClick={() =>
                    onChange([
                        ...conditions,
                        {field: COLLECTION, operator: '=', value: ''},
                    ])
                }
            >
                <PlusIcon /> {t('asset_policy.add_condition', 'Add condition')}
            </Button>
        </div>
    );
}

/** The tree does not unfold to it: its path, spelled out */
function SelectedCollection({id}: {id: string}) {
    const {t} = useTranslation();
    const collection = useQuery({
        queryKey: ['collection', id],
        queryFn: () => getCollection(id),
        enabled: !!id,
    });
    if (!id) {
        return (
            <p className="mb-1 text-xs text-muted-foreground">
                {t('asset_policy.pick_collection', 'Pick a collection:')}
            </p>
        );
    }

    return (
        <p className="mb-1 truncate text-xs font-medium">
            {collection.data
                ? (collection.data.absoluteDisplayName ??
                  collection.data.displayName)
                : collection.isError
                  ? id
                  : '…'}
        </p>
    );
}

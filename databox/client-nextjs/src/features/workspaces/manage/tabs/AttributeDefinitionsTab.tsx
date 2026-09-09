'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {LockIcon, SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {
    AttributeDefinition,
    AttributePolicy,
    EntityList,
} from '@/types/api';
import {AssetType, AttributeType, EntityName} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {
    deleteAttributeDefinition,
    getAttributeFieldTypes,
    getAttributePolicies,
    getEntityLists,
    getWorkspaceAttributeDefinitions,
    postAttributeDefinition,
    putAttributeDefinition,
    sortAttributeDefinitions,
} from '@/lib/api/metadata';
import {getRenditionDefinitions} from '@/lib/api/misc';
import {Button} from '@/components/ui/button';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {Checkbox, LabeledControl, Switch} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {Badge} from '@/components/ui/misc';
import {TranslatableField} from '@/components/form/TranslatableField';
import {iri} from '@/lib/utils/iri';
import {useDefinitionsStore} from '@/features/attributes/definitionsStore';

export function AttributeDefinitionsTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const definitions = useQuery({
        queryKey: ['attribute-definitions', 'manage', workspace.id],
        queryFn: () =>
            getWorkspaceAttributeDefinitions({workspaceId: workspace.id}),
    });
    const policies = useQuery({
        queryKey: ['attribute-policies', workspace.id],
        queryFn: () => getAttributePolicies(workspace.id),
    });
    const entityLists = useQuery({
        queryKey: ['entity-lists', workspace.id],
        queryFn: () => getEntityLists({workspaceId: workspace.id}),
    });
    const fieldTypes = useQuery({
        queryKey: ['field-types'],
        queryFn: getAttributeFieldTypes,
        staleTime: Infinity,
    });
    const renditions = useQuery({
        queryKey: ['rendition-definitions', workspace.id],
        queryFn: () => getRenditionDefinitions({workspaceIds: [workspace.id]}),
    });
    const reloadStore = useDefinitionsStore(s => s.loadWorkspace);

    const changed = async () => {
        await definitions.refetch();
        await reloadStore(workspace.id);
    };

    return (
        <DefinitionManager<AttributeDefinition>
            items={definitions.data?.items}
            loading={definitions.isLoading}
            onChanged={changed}
            onSort={sortAttributeDefinitions}
            filter={(d, q) =>
                (d.displayName ?? d.name).toLowerCase().includes(q) ||
                d.slug.includes(q)
            }
            renderItem={d => (
                <span className="flex items-center gap-2">
                    <span className="min-w-0 flex-1 truncate">
                        {d.displayName ?? d.name}
                        <span className="ml-1 font-mono text-[11px] text-muted-foreground">
                            {d.slug}
                        </span>
                    </span>
                    <Badge variant="muted">{d.type}</Badge>
                    {d.multiple ? (
                        <Badge variant="secondary">
                            {t('attribute_def.multiple', 'multi')}
                        </Badge>
                    ) : null}
                    {!d.enabled ? (
                        <Badge variant="destructive">
                            {t('common.disabled', 'disabled')}
                        </Badge>
                    ) : null}
                    {!d.editable ? (
                        <LockIcon className="size-3.5 text-muted-foreground" />
                    ) : null}
                </span>
            )}
            onDelete={d => deleteAttributeDefinition(d.id)}
            createLabel={t('attribute_def.create', 'New attribute')}
            renderForm={(d, onSaved) => (
                <DefinitionForm
                    key={d?.id ?? 'new'}
                    definition={d}
                    workspace={workspace}
                    policies={policies.data?.items ?? []}
                    entityLists={entityLists.data?.items ?? []}
                    fieldTypes={
                        fieldTypes.data?.map(f => ({
                            value: f.name,
                            label: f.displayName ?? f.name,
                        })) ??
                        Object.values(AttributeType).map(v => ({
                            value: v,
                            label: v,
                        }))
                    }
                    renditionNames={
                        renditions.data?.items.map(r => r.name) ?? []
                    }
                    onSaved={onSaved}
                />
            )}
        />
    );
}

function DefinitionForm({
    definition: d,
    workspace,
    policies,
    entityLists,
    fieldTypes,
    renditionNames,
    onSaved,
}: {
    definition?: AttributeDefinition;
    workspace: WorkspaceTabProps['workspace'];
    policies: AttributePolicy[];
    entityLists: EntityList[];
    fieldTypes: {value: string; label: string}[];
    renditionNames: string[];
    onSaved: (d: AttributeDefinition) => void;
}) {
    const {t} = useTranslation();
    const [form, setForm] = useState({
        name: d?.name ?? '',
        translations: d?.translations?.name ?? {},
        slug: d?.slug ?? '',
        type: d?.type ?? AttributeType.Text,
        enabled: d?.enabled ?? true,
        policy:
            typeof d?.policy === 'string'
                ? d.policy
                : (d?.policy?.['@id'] ?? ''),
        entityList:
            typeof d?.entityList === 'string'
                ? d.entityList
                : (d?.entityList?.['@id'] ?? ''),
        target: String(d?.target ?? AssetType.Asset),
        searchable: d?.searchable ?? true,
        editable: d?.editable ?? true,
        editableInGui: d?.editableInGui ?? true,
        sortable: d?.sortable ?? false,
        suggest: d?.suggest ?? false,
        translatable: d?.translatable ?? false,
        multiple: d?.multiple ?? false,
        allowInvalid: d?.allowInvalid ?? false,
        facetEnabled: d?.facetEnabled ?? false,
        fallback: d?.fallback ?? {},
        initialValues: d?.initialValues ?? {},
        readFromMetadata: (d?.readFromMetadata ?? []).join('\n'),
        writeMetadata: (d?.writeMetadata ?? []).join('\n'),
        writeAllRenditions:
            !d?.writeMetadataRenditions ||
            d.writeMetadataRenditions.length === 0,
        writeMetadataRenditions: d?.writeMetadataRenditions ?? [],
        fillFromName: d?.fillFromName ?? false,
        namePriority: d?.namePriority?.toString() ?? '',
        searchBoost: d?.searchBoost?.toString() ?? '1',
    });
    const [saving, setSaving] = useState(false);
    const set = <K extends keyof typeof form>(k: K, v: (typeof form)[K]) =>
        setForm(f => ({...f, [k]: v}));
    const locales = workspace.enabledLocales ?? [];

    const save = async () => {
        setSaving(true);
        try {
            const lines = (s: string) =>
                s
                    .split('\n')
                    .map(l => l.trim())
                    .filter(Boolean);
            const data: Partial<AttributeDefinition> = {
                name: form.name,
                translations: {name: form.translations},
                slug: form.slug || undefined,
                type: form.type as AttributeType,
                enabled: form.enabled,
                policy: form.policy || null,
                entityList:
                    form.type === AttributeType.Entity
                        ? form.entityList || null
                        : null,
                target: Number(form.target) as AssetType,
                searchable: form.searchable,
                editable: form.editable,
                editableInGui: form.editableInGui,
                sortable: form.sortable,
                suggest: form.suggest,
                translatable: form.translatable,
                multiple: form.multiple,
                allowInvalid: form.allowInvalid,
                facetEnabled: form.facetEnabled,
                fallback: form.fallback,
                initialValues: form.initialValues,
                readFromMetadata: lines(form.readFromMetadata),
                writeMetadata: lines(form.writeMetadata),
                writeMetadataRenditions: form.writeAllRenditions
                    ? []
                    : form.writeMetadataRenditions,
                fillFromName: form.fillFromName,
                namePriority:
                    form.fillFromName && form.namePriority
                        ? Number(form.namePriority)
                        : null,
                searchBoost: Number(form.searchBoost) || 1,
            };
            const saved = d
                ? await putAttributeDefinition(d.id, data)
                : await postAttributeDefinition({
                      ...data,
                      workspace: iri(EntityName.Workspace, workspace.id),
                  });
            toast.success(t('attribute_def.saved', 'Attribute saved'));
            onSaved(saved);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    const flag = (k: keyof typeof form, label: string) => (
        <LabeledControl key={k} label={label}>
            <Checkbox
                checked={!!form[k]}
                onCheckedChange={v => set(k, (v === true) as never)}
            />
        </LabeledControl>
    );

    return (
        <div className="space-y-4">
            <TranslatableField
                label={t('common.name', 'Name')}
                value={form.name}
                onChange={v => set('name', v)}
                translations={form.translations}
                onTranslationsChange={v => set('translations', v)}
                locales={locales}
            />
            <div className="grid gap-3 sm:grid-cols-2">
                <FormRow
                    label={t('attribute_def.slug', 'Slug')}
                    help={t(
                        'attribute_def.slug_help',
                        'Generated from the name when empty'
                    )}
                >
                    <Input
                        value={form.slug}
                        onChange={e => set('slug', e.target.value)}
                        className="font-mono"
                    />
                </FormRow>
                <FormRow label={t('attribute_def.type', 'Type')}>
                    <SimpleSelect
                        value={form.type}
                        onValueChange={v => set('type', v as AttributeType)}
                        options={fieldTypes}
                        disabled={!!d}
                    />
                </FormRow>
                <FormRow label={t('attribute_def.policy', 'Policy')}>
                    <SimpleSelect
                        value={form.policy || '__none'}
                        onValueChange={v =>
                            set('policy', v === '__none' ? '' : v)
                        }
                        options={[
                            {value: '__none', label: t('common.none', 'None')},
                            ...policies.map(p => ({
                                value: p['@id'],
                                label: p.name,
                            })),
                        ]}
                    />
                </FormRow>
                {form.type === AttributeType.Entity ? (
                    <FormRow
                        label={t('attribute_def.entity_list', 'Entity list')}
                    >
                        <SimpleSelect
                            value={form.entityList || '__none'}
                            onValueChange={v =>
                                set('entityList', v === '__none' ? '' : v)
                            }
                            options={[
                                {
                                    value: '__none',
                                    label: t('common.none', 'None'),
                                },
                                ...entityLists.map(l => ({
                                    value: l['@id'],
                                    label: l.name,
                                })),
                            ]}
                        />
                    </FormRow>
                ) : null}
                <FormRow label={t('attribute_def.target', 'Applies to')}>
                    <SimpleSelect
                        value={form.target}
                        onValueChange={v => set('target', v)}
                        options={[
                            {
                                value: String(AssetType.Asset),
                                label: t('asset_type.asset', 'Assets'),
                            },
                            {
                                value: String(AssetType.Story),
                                label: t('asset_type.story', 'Stories'),
                            },
                            {
                                value: String(AssetType.Both),
                                label: t(
                                    'asset_type.both',
                                    'Assets and stories'
                                ),
                            },
                        ]}
                    />
                </FormRow>
                <FormRow
                    label={t('attribute_def.search_boost', 'Search boost')}
                >
                    <Input
                        type="number"
                        step="0.1"
                        value={form.searchBoost}
                        onChange={e => set('searchBoost', e.target.value)}
                    />
                </FormRow>
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
                <LabeledControl label={t('common.enabled', 'Enabled')}>
                    <Switch
                        checked={form.enabled}
                        onCheckedChange={v => set('enabled', v)}
                    />
                </LabeledControl>
                {flag(
                    'searchable',
                    t('attribute_def.searchable', 'Searchable')
                )}
                {flag('editable', t('attribute_def.editable', 'Editable'))}
                {flag(
                    'editableInGui',
                    t(
                        'attribute_def.editable_in_gui',
                        'Editable in the interface'
                    )
                )}
                {flag('sortable', t('attribute_def.sortable', 'Sortable'))}
                {flag(
                    'suggest',
                    t('attribute_def.suggest', 'Used in suggestions')
                )}
                {flag(
                    'translatable',
                    t('attribute_def.translatable', 'Translatable')
                )}
                {flag(
                    'multiple',
                    t('attribute_def.multiple_values', 'Multiple values')
                )}
                {flag(
                    'allowInvalid',
                    t('attribute_def.allow_invalid', 'Allow invalid values')
                )}
                {flag('facetEnabled', t('attribute_def.facet', 'Facet'))}
                {flag(
                    'fillFromName',
                    t(
                        'attribute_def.fill_from_name',
                        'Fill from asset name / use as name'
                    )
                )}
            </div>
            {form.fillFromName ? (
                <FormRow
                    label={t('attribute_def.name_priority', 'Name priority')}
                >
                    <Input
                        type="number"
                        value={form.namePriority}
                        onChange={e => set('namePriority', e.target.value)}
                        className="w-32"
                    />
                </FormRow>
            ) : null}
            <div className="grid gap-3 sm:grid-cols-2">
                <FormRow
                    label={t(
                        'attribute_def.read_metadata',
                        'Read from metadata (one tag per line)'
                    )}
                >
                    <Textarea
                        value={form.readFromMetadata}
                        onChange={e => set('readFromMetadata', e.target.value)}
                        className="font-mono text-xs"
                        placeholder="IPTC:Headline"
                    />
                </FormRow>
                <FormRow
                    label={t(
                        'attribute_def.write_metadata',
                        'Write metadata (one tag per line)'
                    )}
                >
                    <Textarea
                        value={form.writeMetadata}
                        onChange={e => set('writeMetadata', e.target.value)}
                        className="font-mono text-xs"
                    />
                </FormRow>
            </div>
            {form.writeMetadata.trim() ? (
                <div className="space-y-2">
                    <LabeledControl
                        label={t(
                            'attribute_def.write_all_renditions',
                            'Write metadata to all renditions'
                        )}
                    >
                        <Checkbox
                            checked={form.writeAllRenditions}
                            onCheckedChange={v =>
                                set('writeAllRenditions', v === true)
                            }
                        />
                    </LabeledControl>
                    {!form.writeAllRenditions ? (
                        <div className="flex flex-wrap gap-2 pl-6">
                            {renditionNames.map(n => (
                                <LabeledControl key={n} label={n}>
                                    <Checkbox
                                        checked={form.writeMetadataRenditions.includes(
                                            n
                                        )}
                                        onCheckedChange={v =>
                                            set(
                                                'writeMetadataRenditions',
                                                v
                                                    ? [
                                                          ...form.writeMetadataRenditions,
                                                          n,
                                                      ]
                                                    : form.writeMetadataRenditions.filter(
                                                          x => x !== n
                                                      )
                                            )
                                        }
                                    />
                                </LabeledControl>
                            ))}
                        </div>
                    ) : null}
                </div>
            ) : null}
            <div className="grid gap-3 sm:grid-cols-2">
                <LocaleValues
                    label={t(
                        'attribute_def.fallback',
                        'Fallback (Twig template per locale)'
                    )}
                    values={form.fallback}
                    onChange={v => set('fallback', v)}
                    locales={locales}
                />
                <LocaleValues
                    label={t(
                        'attribute_def.initial_values',
                        'Initial values (Twig template per locale)'
                    )}
                    values={form.initialValues}
                    onChange={v => set('initialValues', v)}
                    locales={locales}
                />
            </div>
            {d?.lastErrors && d.lastErrors.length > 0 ? (
                <div className="rounded-md border border-destructive/40 bg-destructive/5 p-2 text-xs">
                    <div className="mb-1 font-semibold text-destructive">
                        {t('attribute_def.last_errors', 'Last errors')}
                    </div>
                    <ul className="space-y-0.5 font-mono">
                        {d.lastErrors.map((e, i) => (
                            <li key={i}>
                                {e.date}: {e.message}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
            <div className="flex justify-end">
                <Button
                    onClick={save}
                    loading={saving}
                    disabled={!form.name.trim()}
                >
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

function LocaleValues({
    label,
    values,
    onChange,
    locales,
}: {
    label: string;
    values: Record<string, string>;
    onChange: (v: Record<string, string>) => void;
    locales: string[];
}) {
    const keys = [...new Set(['_', ...locales, ...Object.keys(values)])];

    return (
        <FormRow label={label}>
            <div className="space-y-1">
                {keys.map(k => (
                    <div key={k} className="flex items-center gap-2">
                        <span className="w-10 shrink-0 font-mono text-xs text-muted-foreground">
                            {k === '_' ? '*' : k}
                        </span>
                        <Input
                            value={
                                values[k === '_' ? 'fallback' : k] ??
                                values[k] ??
                                ''
                            }
                            onChange={e => {
                                const next = {...values};
                                const key = k === '_' ? 'fallback' : k;
                                if (e.target.value) {
                                    next[key] = e.target.value;
                                } else {
                                    delete next[key];
                                }
                                onChange(next);
                            }}
                            className="font-mono text-xs"
                            placeholder="{{ file.name }}"
                        />
                    </div>
                ))}
            </div>
        </FormRow>
    );
}

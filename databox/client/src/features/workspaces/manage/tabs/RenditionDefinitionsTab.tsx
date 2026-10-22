'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {RenditionDefinition, RenditionPolicy} from '@/types/api';
import {AssetType, EntityName, RenditionBuildMode} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {
    deleteRenditionDefinition,
    duplicateRenditionDefinition,
    getRenditionBuildReference,
    getRenditionDefinition,
    getRenditionDefinitions,
    getRenditionPolicies,
    postRenditionDefinition,
    putRenditionDefinition,
    sortRenditionDefinitions,
} from '@/lib/api/misc';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {CodeEditor} from '@/components/form/code/CodeEditor';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {Badge} from '@/components/ui/misc';
import {ReferenceDetails} from '@/components/form/ReferenceDetails';
import {iri} from '@/lib/utils/iri';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';
import {useInfiniteList} from '@/hooks/useInfiniteList';
import {RenditionDefinitionSelect} from '@/components/form/selects';

export function RenditionDefinitionsTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const definitions = useInfiniteList<RenditionDefinition>({
        queryKey: ['rendition-definitions', 'manage', workspace.id],
        fetchPage: ({query, url, signal}) =>
            getRenditionDefinitions(
                {
                    workspaceId: workspace.id,
                    query: query || undefined,
                    limit: 50,
                    url,
                },
                signal
            ),
    });
    const policies = useQuery({
        queryKey: ['rendition-policies', workspace.id],
        queryFn: () => getRenditionPolicies(workspace.id),
    });

    return (
        <DefinitionManager<RenditionDefinition>
            items={definitions.items}
            loading={definitions.loading}
            error={definitions.error}
            onChanged={() =>
                queryClient.invalidateQueries({
                    queryKey: ['rendition-definitions'],
                })
            }
            onSort={sortRenditionDefinitions}
            query={definitions.query}
            onQueryChange={definitions.setQuery}
            hasMore={definitions.hasMore}
            loadingMore={definitions.loadingMore}
            onReachEnd={definitions.loadMore}
            complete={definitions.complete}
            onLoadAll={definitions.loadAll}
            getItem={getRenditionDefinition}
            itemQueryKey={['rendition-definitions', 'item']}
            renderItem={d => (
                <span className="flex items-center gap-2">
                    <span className="min-w-0 flex-1 truncate">
                        {d.displayName ?? d.name}
                    </span>
                    {d.useAsMain ? (
                        <Badge variant="secondary">main</Badge>
                    ) : null}
                    {d.useAsPreview ? (
                        <Badge variant="secondary">preview</Badge>
                    ) : null}
                    {d.useAsThumbnail ? (
                        <Badge variant="secondary">thumb</Badge>
                    ) : null}
                    {d.useAsAnimatedThumbnail ? (
                        <Badge variant="secondary">animated thumb</Badge>
                    ) : null}
                </span>
            )}
            onDuplicate={d => duplicateRenditionDefinition(d.id)}
            onDelete={d => deleteRenditionDefinition(d.id)}
            createLabel={t('rendition_def.create', 'New rendition')}
            renderForm={(d, onSaved) => (
                <DefinitionForm
                    key={d?.id ?? 'new'}
                    definition={d}
                    workspaceId={workspace.id}
                    policies={policies.data?.items ?? []}
                    onSaved={onSaved}
                />
            )}
        />
    );
}

function DefinitionForm({
    definition: d,
    workspaceId,
    policies,
    onSaved,
}: {
    definition?: RenditionDefinition;
    workspaceId: string;
    policies: RenditionPolicy[];
    onSaved: (d: RenditionDefinition) => void;
}) {
    const {t} = useTranslation();
    const [form, setForm] = useState({
        name: d?.name ?? '',
        policy:
            typeof d?.policy === 'string'
                ? d.policy
                : (d?.policy?.['@id'] ?? ''),
        parent:
            typeof d?.parent === 'string'
                ? d.parent
                : (d?.parent?.['@id'] ?? ''),
        target: String(d?.target ?? AssetType.Asset),
        buildMode: String(d?.buildMode ?? RenditionBuildMode.Custom),
        definition: d?.definition ?? '',
        substitutable: d?.substitutable ?? true,
        writeMetadata: d?.writeMetadata ?? false,
        useAsMain: d?.useAsMain ?? false,
        useAsPreview: d?.useAsPreview ?? false,
        useAsThumbnail: d?.useAsThumbnail ?? false,
        useAsAnimatedThumbnail: d?.useAsAnimatedThumbnail ?? false,
    });
    const [saving, setSaving] = useState(false);
    const {markSaved} = useDirtyState(form);
    const reference = useQuery({
        queryKey: ['rendition-build-reference'],
        queryFn: getRenditionBuildReference,
        staleTime: Infinity,
    });
    const set = <K extends keyof typeof form>(k: K, v: (typeof form)[K]) =>
        setForm(f => ({...f, [k]: v}));

    const save = async () => {
        setSaving(true);
        try {
            const data: Partial<RenditionDefinition> = {
                name: form.name,
                policy: form.policy || null,
                parent: form.parent || null,
                target: Number(form.target) as AssetType,
                buildMode: Number(form.buildMode) as RenditionBuildMode,
                definition: form.definition,
                substitutable: form.substitutable,
                writeMetadata: form.writeMetadata,
                useAsMain: form.useAsMain,
                useAsPreview: form.useAsPreview,
                useAsThumbnail: form.useAsThumbnail,
                useAsAnimatedThumbnail: form.useAsAnimatedThumbnail,
            };
            const saved = d
                ? await putRenditionDefinition(d.id, data)
                : await postRenditionDefinition({
                      ...data,
                      workspace: iri(EntityName.Workspace, workspaceId),
                  });
            toast.success(
                t('rendition_def.saved', 'Rendition definition saved')
            );
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
            <div className="grid gap-3 sm:grid-cols-2">
                <FormRow label={t('common.name', 'Name')}>
                    <Input
                        value={form.name}
                        onChange={e => set('name', e.target.value)}
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
                <FormRow
                    label={t(
                        'rendition_def.parent',
                        'Parent (source rendition)'
                    )}
                >
                    <RenditionDefinitionSelect
                        workspaceId={workspaceId}
                        useIri
                        exclude={d?.id}
                        value={form.parent || undefined}
                        onChange={v => set('parent', v ?? '')}
                        placeholder={t(
                            'rendition_def.parent_source',
                            'Source file'
                        )}
                    />
                </FormRow>
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
                <FormRow label={t('rendition_def.build_mode', 'Build mode')}>
                    <SimpleSelect
                        value={form.buildMode}
                        onValueChange={v => set('buildMode', v)}
                        options={[
                            {
                                value: String(RenditionBuildMode.None),
                                label: t(
                                    'rendition_def.build_none',
                                    'No automatic build'
                                ),
                            },
                            {
                                value: String(RenditionBuildMode.PickSource),
                                label: t(
                                    'rendition_def.build_pick_source',
                                    'Use the source file'
                                ),
                            },
                            {
                                value: String(RenditionBuildMode.Custom),
                                label: t(
                                    'rendition_def.build_custom',
                                    'Custom definition'
                                ),
                            },
                        ]}
                    />
                </FormRow>
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
                {(
                    [
                        'substitutable',
                        'writeMetadata',
                        'useAsMain',
                        'useAsPreview',
                        'useAsThumbnail',
                        'useAsAnimatedThumbnail',
                    ] as const
                ).map(k => (
                    <LabeledControl
                        key={k}
                        label={t(
                            `rendition_def.${k}`,
                            k.replace(/([A-Z])/g, ' $1').toLowerCase()
                        )}
                    >
                        <Checkbox
                            checked={form[k]}
                            onCheckedChange={v => set(k, v === true)}
                        />
                    </LabeledControl>
                ))}
            </div>
            {Number(form.buildMode) === RenditionBuildMode.Custom ? (
                <div className="space-y-2">
                    <FormRow
                        label={t(
                            'rendition_def.definition',
                            'Build definition (YAML)'
                        )}
                    >
                        <CodeEditor
                            mode="twig"
                            minLines={12}
                            value={form.definition}
                            onChange={v => set('definition', v)}
                        />
                    </FormRow>
                    {reference.data ? (
                        <ReferenceDetails
                            sections={reference.data.references}
                            title={t('rendition_def.reference', 'Reference')}
                        />
                    ) : null}
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

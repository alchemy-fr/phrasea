'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {BookOpenIcon, SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {RenditionDefinition, RenditionPolicy} from '@/types/api';
import {AssetType, EntityName, RenditionBuildMode} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {
    deleteRenditionDefinition,
    getRenditionBuildReference,
    getRenditionDefinitions,
    getRenditionPolicies,
    postRenditionDefinition,
    putRenditionDefinition,
    sortRenditionDefinitions,
} from '@/lib/api/misc';
import {Button} from '@/components/ui/button';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {Badge} from '@/components/ui/misc';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/overlays';
import {CopyButton} from '@/components/ui/copy-button';
import {iri} from '@/lib/utils/iri';

export function RenditionDefinitionsTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const definitions = useQuery({
        queryKey: ['rendition-definitions', 'manage', workspace.id],
        queryFn: () => getRenditionDefinitions({workspaceIds: [workspace.id]}),
    });
    const policies = useQuery({
        queryKey: ['rendition-policies', workspace.id],
        queryFn: () => getRenditionPolicies(workspace.id),
    });

    return (
        <DefinitionManager<RenditionDefinition>
            items={definitions.data?.items}
            loading={definitions.isLoading}
            onChanged={() => definitions.refetch()}
            onSort={sortRenditionDefinitions}
            filter={(d, q) =>
                (d.displayName ?? d.name).toLowerCase().includes(q)
            }
            renderItem={d => (
                <span className="flex items-center gap-2">
                    <span className="min-w-0 flex-1 truncate">
                        {d.displayName ?? d.name}
                    </span>
                    {d.useAsThumbnail ? (
                        <Badge variant="secondary">thumb</Badge>
                    ) : null}
                    {d.useAsPreview ? (
                        <Badge variant="secondary">preview</Badge>
                    ) : null}
                    {d.useAsMain ? (
                        <Badge variant="secondary">main</Badge>
                    ) : null}
                    {d.substitutable ? (
                        <Badge variant="muted">
                            {t('rendition_def.substitutable', 'substitutable')}
                        </Badge>
                    ) : null}
                </span>
            )}
            onDelete={d => deleteRenditionDefinition(d.id)}
            createLabel={t('rendition_def.create', 'New rendition')}
            renderForm={(d, onSaved) => (
                <DefinitionForm
                    key={d?.id ?? 'new'}
                    definition={d}
                    workspaceId={workspace.id}
                    policies={policies.data?.items ?? []}
                    all={definitions.data?.items ?? []}
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
    all,
    onSaved,
}: {
    definition?: RenditionDefinition;
    workspaceId: string;
    policies: RenditionPolicy[];
    all: RenditionDefinition[];
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
                    <SimpleSelect
                        value={form.parent || '__none'}
                        onValueChange={v =>
                            set('parent', v === '__none' ? '' : v)
                        }
                        options={[
                            {
                                value: '__none',
                                label: t(
                                    'rendition_def.parent_source',
                                    'Source file'
                                ),
                            },
                            ...all
                                .filter(x => x.id !== d?.id)
                                .map(x => ({
                                    value: x['@id'],
                                    label: x.displayName ?? x.name,
                                })),
                        ]}
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
                <FormRow
                    label={
                        <span className="flex items-center gap-2">
                            {t(
                                'rendition_def.definition',
                                'Build definition (YAML)'
                            )}
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-6 text-xs"
                                    >
                                        <BookOpenIcon />{' '}
                                        {t(
                                            'rendition_def.reference',
                                            'Reference'
                                        )}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="max-h-96 w-[32rem] overflow-auto">
                                    {reference.data ? (
                                        <div className="space-y-3 text-xs">
                                            {reference.data.references.map(
                                                r => (
                                                    <div key={r.name}>
                                                        <div className="flex items-center gap-1 font-semibold">
                                                            {r.name}{' '}
                                                            <CopyButton
                                                                value={
                                                                    r.reference
                                                                }
                                                            />
                                                        </div>
                                                        {r.description ? (
                                                            <p className="text-muted-foreground">
                                                                {r.description}
                                                            </p>
                                                        ) : null}
                                                        <pre className="mt-1 rounded bg-muted p-2 font-mono">
                                                            {r.reference}
                                                        </pre>
                                                    </div>
                                                )
                                            )}
                                        </div>
                                    ) : null}
                                </PopoverContent>
                            </Popover>
                        </span>
                    }
                >
                    <Textarea
                        value={form.definition}
                        onChange={e => set('definition', e.target.value)}
                        className="min-h-48 font-mono text-xs"
                        spellCheck={false}
                    />
                </FormRow>
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

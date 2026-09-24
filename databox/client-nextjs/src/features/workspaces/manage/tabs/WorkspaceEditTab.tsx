'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {SaveIcon, XIcon, GripVerticalIcon} from 'lucide-react';
import {toast} from 'sonner';
import {AssetStatus} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {putWorkspace} from '@/lib/api/collections';
import {getLocales} from '@/lib/api/metadata';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {TranslatableField} from '@/components/form/TranslatableField';
import {AsyncCombobox} from '@/components/form/AsyncCombobox';
import {assetStatusLabels} from '@/features/attributes/types/registry';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {Flag} from '@/components/ui/flag';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';
import {cn} from '@/lib/utils/cn';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';

export function WorkspaceEditTab({workspace, refresh}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const upsert = useCollectionStore(s => s.upsertWorkspace);
    const [name, setName] = useState(workspace.name);
    const [translations, setTranslations] = useState<Record<string, string>>(
        workspace.translations?.name ?? {}
    );
    const [isPublic, setIsPublic] = useState(workspace.public);
    const [locales, setLocales] = useState<string[]>(
        workspace.enabledLocales ?? []
    );
    const [fallbacks, setFallbacks] = useState<string[]>(
        workspace.localeFallbacks ?? []
    );
    const [retention, setRetention] = useState(
        String(workspace.trashRetentionDelay ?? '')
    );
    const [defaultStatus, setDefaultStatus] = useState(
        String(workspace.assetDefaultStatus ?? AssetStatus.Accepted)
    );
    const [analysisRequired, setAnalysisRequired] = useState(
        !!workspace.fileAnalysisRequired
    );
    const [saving, setSaving] = useState(false);
    const {markSaved} = useDirtyState({
        name,
        translations,
        isPublic,
        locales,
        fallbacks,
        retention,
        defaultStatus,
        analysisRequired,
    });
    const allLocales = useQuery({
        queryKey: ['locales'],
        queryFn: getLocales,
        staleTime: Infinity,
    });

    const save = async () => {
        setSaving(true);
        try {
            const updated = await putWorkspace(workspace.id, {
                name,
                translations: {name: translations},
                public: isPublic,
                enabledLocales: locales,
                localeFallbacks: fallbacks,
                trashRetentionDelay: retention ? Number(retention) : undefined,
                assetDefaultStatus: Number(defaultStatus) as AssetStatus,
                fileAnalysisRequired: analysisRequired,
            } as any);
            upsert(updated);
            markSaved();
            refresh();
            toast.success(t('workspace.saved', 'Workspace saved'));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    const localeOptions = (allLocales.data ?? []).map(l => ({
        value: l.id,
        label: `${l.name} (${l.id})`,
    }));

    return (
        <div className="max-w-2xl space-y-4">
            <TranslatableField
                label={t('workspace.title', 'Title')}
                value={name}
                onChange={setName}
                translations={translations}
                onTranslationsChange={setTranslations}
                locales={locales}
            />
            <LabeledControl
                label={t('common.public', 'Public')}
                description={t(
                    'workspace.public_help',
                    'Public workspaces are visible to every user.'
                )}
            >
                <Switch checked={isPublic} onCheckedChange={setIsPublic} />
            </LabeledControl>
            <FormRow
                label={t(
                    'workspace.enabled_locales',
                    'Enabled locales (ordered)'
                )}
            >
                <LocaleList
                    value={locales}
                    onChange={setLocales}
                    options={localeOptions}
                />
            </FormRow>
            <FormRow
                label={t('workspace.fallback_locales', 'Fallback locales')}
            >
                <LocaleList
                    value={fallbacks}
                    onChange={setFallbacks}
                    options={localeOptions}
                />
            </FormRow>
            <div className="grid gap-4 sm:grid-cols-2">
                <FormRow
                    label={t(
                        'workspace.trash_retention',
                        'Trash retention (days)'
                    )}
                >
                    <Input
                        type="number"
                        min={0}
                        value={retention}
                        onChange={e => setRetention(e.target.value)}
                    />
                </FormRow>
                <FormRow
                    label={t(
                        'workspace.default_status',
                        'Default asset status'
                    )}
                >
                    <SimpleSelect
                        value={defaultStatus}
                        onValueChange={setDefaultStatus}
                        options={Object.entries(assetStatusLabels(t)).map(
                            ([k, label]) => ({value: k, label})
                        )}
                    />
                </FormRow>
            </div>
            <LabeledControl
                label={t(
                    'workspace.analysis_required',
                    'Requires file analysis'
                )}
                description={t(
                    'workspace.analysis_required_help',
                    'New files are quarantined until the analyzers accept them.'
                )}
            >
                <Switch
                    checked={analysisRequired}
                    onCheckedChange={setAnalysisRequired}
                />
            </LabeledControl>
            <div className="flex justify-end">
                <Button onClick={save} loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
        </div>
    );
}

function LocaleList({
    value,
    onChange,
    options,
}: {
    value: string[];
    onChange: (v: string[]) => void;
    options: {value: string; label: string}[];
}) {
    const {t} = useTranslation();
    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 4}}),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const onDragEnd = ({active, over}: DragEndEvent) => {
        if (over && active.id !== over.id) {
            onChange(
                arrayMove(
                    value,
                    value.indexOf(String(active.id)),
                    value.indexOf(String(over.id))
                )
            );
        }
    };

    return (
        <div className="space-y-2">
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={onDragEnd}
            >
                <SortableContext
                    items={value}
                    strategy={verticalListSortingStrategy}
                >
                    <ul className="space-y-1">
                        {value.map(l => (
                            <LocaleRow
                                key={l}
                                locale={l}
                                label={
                                    options.find(o => o.value === l)?.label ?? l
                                }
                                onRemove={() =>
                                    onChange(value.filter(x => x !== l))
                                }
                            />
                        ))}
                    </ul>
                </SortableContext>
            </DndContext>
            <AsyncCombobox
                queryKey={['locale-options']}
                loadOptions={async q =>
                    options
                        .filter(
                            o =>
                                !value.includes(o.value) &&
                                o.label.toLowerCase().includes(q.toLowerCase())
                        )
                        .slice(0, 50)
                }
                value={undefined}
                onChange={v => v && onChange([...value, v])}
                placeholder={t('workspace.add_locale', 'Add a locale…')}
            />
        </div>
    );
}

function LocaleRow({
    locale,
    label,
    onRemove,
}: {
    locale: string;
    label: string;
    onRemove: () => void;
}) {
    const {t} = useTranslation();
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({id: locale});

    return (
        <li
            ref={setNodeRef}
            style={{transform: CSS.Transform.toString(transform), transition}}
            className={cn(
                'flex items-center gap-2 rounded-md border bg-card px-2 py-1 text-sm',
                isDragging && 'relative z-10 shadow-md'
            )}
        >
            <button
                type="button"
                className="cursor-grab text-muted-foreground"
                {...attributes}
                {...listeners}
                aria-label="Drag"
            >
                <GripVerticalIcon className="size-4" />
            </button>
            <Flag locale={locale} />
            <span className="flex-1">{label}</span>
            <Button
                variant="ghost"
                size="icon-xs"
                onClick={onRemove}
                aria-label={t('common.remove', 'Remove')}
            >
                <XIcon />
            </Button>
        </li>
    );
}

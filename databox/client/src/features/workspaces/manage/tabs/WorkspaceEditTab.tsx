'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    FileTextIcon,
    GripVerticalIcon,
    SaveIcon,
    UploadIcon,
    XIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import {AssetStatus} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {
    deleteWorkspaceLogo,
    deleteWorkspaceTermsPdf,
    getWorkspace,
    putWorkspace,
    uploadWorkspaceLogo,
    uploadWorkspaceTermsPdf,
} from '@/lib/api/collections';
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
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {cn} from '@/lib/utils/cn';
import {
    overlayRow,
    SortableOverlay,
    SortableRow,
    sortableMeasuring,
    useSortableRow,
} from '@/components/ui/sortable';
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
    const initialTerms = workspace.terms?.rawText ?? '';
    const initialTermsTranslations = workspace.terms?.translations ?? {};
    const [termsText, setTermsText] = useState(initialTerms);
    const [termsTranslations, setTermsTranslations] = useState<
        Record<string, string>
    >(initialTermsTranslations);
    const [attachTerms, setAttachTerms] = useState(
        !!workspace.terms?.attachToExports
    );
    // A file to upload, '' to remove the current one, undefined: unchanged
    const [termsPdf, setTermsPdf] = useState<File | ''>();
    const [logo, setLogo] = useState<File | ''>();
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
        termsText,
        termsTranslations,
        attachTerms,
        termsPdf,
        logo,
    });
    const allLocales = useQuery({
        queryKey: ['locales'],
        queryFn: getLocales,
        staleTime: Infinity,
    });

    const save = async () => {
        setSaving(true);
        try {
            // Changing the terms makes a new version, to sign again: only
            // sent when edited
            const termsChanged =
                termsText !== initialTerms ||
                JSON.stringify(termsTranslations) !==
                    JSON.stringify(initialTermsTranslations);
            let updated = await putWorkspace(workspace.id, {
                ...(termsChanged
                    ? {terms: termsText, termsTranslations}
                    : undefined),
                attachTermsToExports: attachTerms,
                name,
                translations: {name: translations},
                public: isPublic,
                enabledLocales: locales,
                localeFallbacks: fallbacks,
                trashRetentionDelay: retention ? Number(retention) : undefined,
                assetDefaultStatus: Number(defaultStatus) as AssetStatus,
                fileAnalysisRequired: analysisRequired,
            } as any);
            if (termsPdf) {
                updated = await uploadWorkspaceTermsPdf(workspace.id, termsPdf);
            } else if (termsPdf === '') {
                await deleteWorkspaceTermsPdf(workspace.id);
            }
            if (logo) {
                updated = await uploadWorkspaceLogo(workspace.id, logo);
            } else if (logo === '') {
                await deleteWorkspaceLogo(workspace.id);
            }
            if (termsPdf === '' || logo === '') {
                updated = await getWorkspace(workspace.id);
            }
            setTermsPdf(undefined);
            setLogo(undefined);
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
            <FormRow
                label={t('workspace.logo.label', 'Logo')}
                help={t(
                    'workspace.logo.help',
                    'Custom workspace logo. When none is set, the default service logo is used.'
                )}
            >
                <FilePicker
                    testId="workspace-logo"
                    accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                    value={logo}
                    onChange={setLogo}
                    current={
                        workspace.logo ? (
                            // eslint-disable-next-line @next/next/no-img-element
                            <img
                                src={workspace.logo}
                                alt=""
                                className="max-h-10 max-w-40"
                            />
                        ) : null
                    }
                    uploadLabel={t('workspace.logo.upload', 'Upload a logo')}
                    removeLabel={t('workspace.logo.remove', 'Remove the logo')}
                    removedLabel={t(
                        'workspace.logo.removed',
                        'The logo will be removed'
                    )}
                />
            </FormRow>
            <div className="space-y-4 border-t pt-4">
                <h3 className="text-sm font-semibold">
                    {t('workspace.terms.title', 'Terms & Conditions')}
                </h3>
                <TranslatableField
                    id="workspace-terms"
                    label={t('workspace.terms.text', 'Text')}
                    multiline
                    value={termsText}
                    onChange={setTermsText}
                    translations={termsTranslations}
                    onTranslationsChange={setTermsTranslations}
                    locales={locales}
                    help={t(
                        'workspace.terms.help',
                        'Changing this text or its translations creates a new version: users who signed a previous version will be asked to sign again.'
                    )}
                />
                <FormRow
                    label={t('workspace.terms.pdf', 'PDF')}
                    help={t(
                        'workspace.terms.pdf_help',
                        'You can provide the Terms & Conditions as a PDF instead: it takes precedence over the text above. A new PDF creates a new version.'
                    )}
                >
                    <FilePicker
                        testId="workspace-terms-pdf"
                        accept="application/pdf"
                        value={termsPdf}
                        onChange={setTermsPdf}
                        current={
                            workspace.terms?.pdfUrl ? (
                                <a
                                    href={workspace.terms.pdfUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                                >
                                    <FileTextIcon className="size-4" />
                                    {t(
                                        'workspace.terms.pdf_view',
                                        'Current PDF (v{{version}})',
                                        {version: workspace.terms.version}
                                    )}
                                </a>
                            ) : null
                        }
                        uploadLabel={t(
                            'workspace.terms.pdf_upload',
                            'Upload a PDF'
                        )}
                        removeLabel={t(
                            'workspace.terms.pdf_remove',
                            'Remove the PDF'
                        )}
                        removedLabel={t(
                            'workspace.terms.pdf_removed',
                            'The PDF will be removed'
                        )}
                    />
                </FormRow>
                <LabeledControl
                    label={t(
                        'workspace.terms.attach',
                        'Attach the Terms & Conditions PDF to exports'
                    )}
                >
                    <Switch
                        checked={attachTerms}
                        onCheckedChange={setAttachTerms}
                    />
                </LabeledControl>
            </div>
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
                measuring={sortableMeasuring}
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
                <SortableOverlay>
                    {id => (
                        <LocaleRowView
                            locale={id}
                            label={
                                options.find(o => o.value === id)?.label ?? id
                            }
                            drag={overlayRow}
                            onRemove={() => undefined}
                        />
                    )}
                </SortableOverlay>
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

type LocaleRowProps = {
    locale: string;
    label: string;
    onRemove: () => void;
};

function LocaleRow(props: LocaleRowProps) {
    const drag = useSortableRow(props.locale);

    return <LocaleRowView {...props} drag={drag} />;
}

/** A locale of the list, also rendered as the copy following the pointer */
function LocaleRowView({
    locale,
    label,
    onRemove,
    drag,
}: LocaleRowProps & {drag: SortableRow}) {
    const {t} = useTranslation();

    return (
        <li
            ref={drag.nodeRef}
            style={drag.style}
            className={cn(
                'flex list-none items-center gap-2 rounded-md border bg-card px-2 py-1 text-sm',
                drag.className
            )}
        >
            <button
                type="button"
                className="cursor-grab text-muted-foreground"
                {...drag.handle}
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

/**
 * A file replacing the current one, or removing it, applied on save:
 * `value` is the file picked, '' to remove the current one, undefined to
 * keep it.
 */
function FilePicker({
    accept,
    value,
    onChange,
    current,
    uploadLabel,
    removeLabel,
    removedLabel,
    testId,
}: {
    accept: string;
    value: File | '' | undefined;
    onChange: (v: File | '' | undefined) => void;
    current: React.ReactNode;
    uploadLabel: string;
    removeLabel: string;
    removedLabel: string;
    testId?: string;
}) {
    const {t} = useTranslation();

    return (
        <div className="flex flex-wrap items-center gap-3" data-testid={testId}>
            {value === undefined ? current : null}
            {value ? (
                <span className="text-sm">
                    {t('workspace.file.selected', 'New file: {{name}}', {
                        name: value.name,
                    })}
                </span>
            ) : null}
            {value === '' ? (
                <span className="text-sm text-destructive">{removedLabel}</span>
            ) : null}
            <Button variant="outline" size="sm" asChild>
                <label className="cursor-pointer">
                    <UploadIcon /> {uploadLabel}
                    <input
                        type="file"
                        accept={accept}
                        hidden
                        onChange={e => {
                            const file = e.target.files?.[0];
                            if (file) {
                                onChange(file);
                            }
                            e.target.value = '';
                        }}
                    />
                </label>
            </Button>
            {value !== undefined ? (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onChange(undefined)}
                >
                    {t('workspace.file.cancel', 'Cancel the change')}
                </Button>
            ) : current ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-destructive"
                    onClick={() => onChange('')}
                >
                    {removeLabel}
                </Button>
            ) : null}
        </div>
    );
}

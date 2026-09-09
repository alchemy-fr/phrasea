'use client';

import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useDropzone} from 'react-dropzone';
import {useQuery} from '@tanstack/react-query';
import {FileIcon, LinkIcon, UploadCloudIcon, XIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset, CollectionPrivacyInfo, Privacy, Tag} from '@/types/api';
import {AssetTypeFilter, EntityName} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {Checkbox, LabeledControl, Switch} from '@/components/ui/controls';
import {
    Tabs,
    TabsList,
    TabsTrigger,
    Badge,
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/misc';
import {
    CollectionTreePicker,
    TreeSelection,
} from '@/components/form/CollectionTreePicker';
import {PrivacyField} from '@/components/form/PrivacyField';
import {TagSelect} from '@/components/form/selects';
import {useConfig} from '@/lib/config/ConfigProvider';
import {toDropzoneAccept} from '@/lib/utils/mime';
import {formatFileSize} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';
import {
    destinationToAssetProps,
    extractNameFromUrl,
    getAssetNameFromFile,
    importAssetsFromUrls,
    postAsset,
    uploadAsset,
} from '@/lib/api/assets';
import {getCollectionPrivacyInfo} from '@/lib/api/collections';
import {getAssetDataTemplates} from '@/lib/api/metadata';
import {useUploadStore} from './uploadStore';
import {uniqueId, runWithConcurrency} from '@/lib/utils/misc';
import {useAttributeEditor} from '@/features/attributes/editor/useAttributeEditor';
import {AttributesEditor} from '@/features/attributes/editor/AttributesEditor';
import {indexToCreateActions} from '@/features/attributes/editor/attributeEditorModel';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {iri} from '@/lib/utils/iri';
import {useOptionalResults} from '@/features/search/useOptionalResults';
import {SaveAsTemplateSection} from './SaveAsTemplateSection';
import {AssetDataTemplateSelect} from './AssetDataTemplateSelect';

type Props = ModalProps & {
    files?: File[];
    workspaceId?: string;
    collectionId?: string;
    onUploaded?: (assets: Asset[]) => void;
};

type Source = 'files' | 'urls';

export function UploadDialog({
    open,
    onOpenChange,
    files: initialFiles,
    workspaceId: initialWorkspaceId,
    collectionId,
    onUploaded,
}: Props) {
    const {t, i18n} = useTranslation();
    const config = useConfig();
    const results = useOptionalResults();
    const workspaces = useCollectionStore(s => s.workspaces);
    const [files, setFiles] = useState<File[]>(initialFiles ?? []);
    const [source, setSource] = useState<Source>(
        initialFiles?.length ? 'files' : 'files'
    );
    const [urls, setUrls] = useState('');
    const [importFiles, setImportFiles] = useState(true);
    const [destination, setDestination] = useState<TreeSelection | undefined>(
        () =>
            collectionId && initialWorkspaceId
                ? {
                      iri: iri(EntityName.Collection, collectionId),
                      workspaceId: initialWorkspaceId,
                      collectionId,
                      label: '',
                  }
                : initialWorkspaceId
                  ? {
                        iri: iri(EntityName.Workspace, initialWorkspaceId),
                        workspaceId: initialWorkspaceId,
                        label: '',
                    }
                  : undefined
    );
    const [privacy, setPrivacy] = useState<Privacy | undefined>(undefined);
    const [tags, setTags] = useState<string[]>([]);
    const [quiet, setQuiet] = useState(false);
    const [isStory, setIsStory] = useState(false);
    const [storyTitle, setStoryTitle] = useState('');
    const [storyTags, setStoryTags] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);
    const [templateIds, setTemplateIds] = useState<string[]>([]);
    const uploadStore = useUploadStore();
    const workspaceId = destination?.workspaceId;
    const workspace = workspaces.find(w => w.id === workspaceId);

    const assetEditor = useAttributeEditor({
        workspaceId,
        target: AssetTypeFilter.Asset,
    });
    const storyEditor = useAttributeEditor({
        workspaceId,
        target: AssetTypeFilter.Story,
    });

    const privacyInfo = useQuery<CollectionPrivacyInfo>({
        queryKey: ['collection-privacy', destination?.collectionId],
        queryFn: () => getCollectionPrivacyInfo(destination!.collectionId!),
        enabled: !!destination?.collectionId,
    });

    const templates = useQuery({
        queryKey: [
            'asset-data-templates',
            workspaceId,
            destination?.collectionId,
        ],
        queryFn: () =>
            getAssetDataTemplates({
                workspace: workspaceId!,
                collection: destination?.collectionId,
            }),
        enabled: !!workspaceId,
    });

    const {
        getRootProps,
        getInputProps,
        isDragActive,
        open: openFilePicker,
    } = useDropzone({
        onDrop: accepted => setFiles(prev => [...prev, ...accepted]),
        noClick: true,
        accept: toDropzoneAccept(config.upload.allowedTypes),
    });

    const urlList = useMemo(
        () =>
            urls
                .split('\n')
                .map(u => u.trim())
                .filter(Boolean),
        [urls]
    );
    const invalidUrls = urlList.filter(u => !/^https?:\/\/.+/.test(u));
    const count = source === 'files' ? files.length : urlList.length;
    const canSubmit =
        !!destination &&
        count > 0 &&
        invalidUrls.length === 0 &&
        !submitting &&
        (!isStory || !!storyTitle.trim());

    useEffect(() => {
        if (privacyInfo.data && !privacyInfo.data.canEditAssetPrivacy) {
            setPrivacy(undefined);
        }
    }, [privacyInfo.data]);

    const submit = async () => {
        if (!destination) {
            return;
        }
        setSubmitting(true);
        const attributes = indexToCreateActions(
            assetEditor.attributes,
            assetEditor.definitions
        );
        const storyAttributes = indexToCreateActions(
            storyEditor.attributes,
            storyEditor.definitions
        );
        const baseAsset = {
            privacy,
            tags: tags.map(id => iri(EntityName.Tag, id)),
            attributes: attributes.length > 0 ? attributes : undefined,
        };
        const story = isStory
            ? {
                  name: storyTitle.trim(),
                  tags: storyTags.map(id => iri(EntityName.Tag, id)),
                  attributes:
                      storyAttributes.length > 0 ? storyAttributes : undefined,
              }
            : undefined;

        try {
            let created: Asset[] = [];
            if (source === 'urls') {
                created = await importAssetsFromUrls(
                    urlList.map(url => ({
                        url,
                        importFile: importFiles,
                        asset: {
                            ...baseAsset,
                            name: extractNameFromUrl(url),
                            ...destinationToAssetProps(destination.iri),
                        },
                    })),
                    {quiet, story}
                );
                toast.success(
                    t('upload.imported', '{{count}} asset(s) imported', {
                        count: created.length,
                    })
                );
            } else {
                let destinationProps = destinationToAssetProps(destination.iri);
                if (story) {
                    const storyAsset = await postAsset(
                        {isStory: true, ...story, ...destinationProps},
                        {quiet}
                    );
                    destinationProps = {
                        collection: storyAsset.storyCollection!['@id'],
                    };
                }
                const pending = files.map(file => ({id: uniqueId(), file}));
                pending.forEach(p =>
                    uploadStore.add({id: p.id, file: p.file, progress: 0})
                );
                onOpenChange(false);
                created = (
                    await runWithConcurrency(
                        pending.map(p => async () => {
                            try {
                                const asset = await uploadAsset(
                                    p.file,
                                    {
                                        ...baseAsset,
                                        name: getAssetNameFromFile(p.file),
                                        ...destinationProps,
                                    },
                                    {
                                        quiet,
                                        onProgress: pr =>
                                            uploadStore.setProgress(
                                                p.id,
                                                pr.loaded / pr.total
                                            ),
                                    }
                                );
                                uploadStore.setProgress(p.id, 1);

                                return asset;
                            } catch (e: any) {
                                uploadStore.setError(
                                    p.id,
                                    e?.message ?? String(e)
                                );

                                return undefined;
                            }
                        }),
                        2
                    )
                ).filter((a): a is Asset => !!a);
                if (created.length > 0) {
                    toast.success(
                        t('upload.done', '{{count}} asset(s) uploaded', {
                            count: created.length,
                        })
                    );
                }
            }
            onUploaded?.(created);
            void results?.reload();
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={o => {
                if (
                    !o &&
                    (files.length > 0 || urls) &&
                    !submitting &&
                    !window.confirm(
                        t('upload.discard_confirm', 'Discard pending files?')
                    )
                ) {
                    return;
                }
                onOpenChange(o);
            }}
        >
            <DialogContent size="xl" className="h-[90dvh]">
                <DialogHeader>
                    <DialogTitle>{t('upload.title', 'Add assets')}</DialogTitle>
                </DialogHeader>
                <DialogBody>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="space-y-4">
                            <Tabs
                                value={source}
                                onValueChange={v => setSource(v as Source)}
                            >
                                <TabsList>
                                    <TabsTrigger value="files">
                                        <UploadCloudIcon />{' '}
                                        {t('upload.files', 'Files')}
                                        {files.length > 0 ? (
                                            <Badge variant="muted">
                                                {files.length}
                                            </Badge>
                                        ) : null}
                                    </TabsTrigger>
                                    <TabsTrigger value="urls">
                                        <LinkIcon /> {t('upload.urls', 'URLs')}
                                        {urlList.length > 0 ? (
                                            <Badge variant="muted">
                                                {urlList.length}
                                            </Badge>
                                        ) : null}
                                    </TabsTrigger>
                                </TabsList>
                            </Tabs>
                            {source === 'files' ? (
                                <div
                                    {...getRootProps({
                                        className: cn(
                                            'rounded-lg border-2 border-dashed p-4 transition-colors',
                                            isDragActive &&
                                                'border-primary bg-primary/5'
                                        ),
                                    })}
                                >
                                    <input {...getInputProps()} />
                                    {files.length === 0 ? (
                                        <button
                                            type="button"
                                            className="flex w-full flex-col items-center gap-2 py-8 text-sm text-muted-foreground"
                                            onClick={openFilePicker}
                                        >
                                            <UploadCloudIcon className="size-8" />
                                            {t(
                                                'upload.drop_or_browse',
                                                'Drop files here or click to browse'
                                            )}
                                        </button>
                                    ) : (
                                        <>
                                            <ul className="max-h-72 space-y-1 overflow-y-auto">
                                                {files.map((f, i) => (
                                                    <FileRow
                                                        key={`${f.name}-${i}`}
                                                        file={f}
                                                        lang={i18n.language}
                                                        onRemove={() =>
                                                            setFiles(prev =>
                                                                prev.filter(
                                                                    (_, j) =>
                                                                        j !== i
                                                                )
                                                            )
                                                        }
                                                    />
                                                ))}
                                            </ul>
                                            <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                                                <span>
                                                    {t(
                                                        'upload.file_count',
                                                        '{{count}} file(s)',
                                                        {count: files.length}
                                                    )}
                                                </span>
                                                <div className="flex gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={openFilePicker}
                                                    >
                                                        {t(
                                                            'upload.add_more',
                                                            'Add more'
                                                        )}
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setFiles([])
                                                        }
                                                    >
                                                        {t(
                                                            'common.reset',
                                                            'Reset'
                                                        )}
                                                    </Button>
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <Textarea
                                        value={urls}
                                        onChange={e => setUrls(e.target.value)}
                                        placeholder={
                                            'https://example.com/image.jpg\nhttps://example.com/video.mp4'
                                        }
                                        className={cn(
                                            'min-h-40 font-mono text-xs',
                                            invalidUrls.length > 0 &&
                                                'border-destructive'
                                        )}
                                        spellCheck={false}
                                    />
                                    {invalidUrls.length > 0 ? (
                                        <p className="text-xs text-destructive">
                                            {t(
                                                'upload.invalid_urls',
                                                '{{count}} invalid URL(s)',
                                                {count: invalidUrls.length}
                                            )}
                                        </p>
                                    ) : null}
                                    <LabeledControl
                                        label={t(
                                            'upload.import_files',
                                            'Import the file(s)'
                                        )}
                                        description={t(
                                            'upload.import_files_help',
                                            'Copy the file into the storage instead of referencing the remote URL.'
                                        )}
                                    >
                                        <Checkbox
                                            checked={importFiles}
                                            onCheckedChange={v =>
                                                setImportFiles(v === true)
                                            }
                                        />
                                    </LabeledControl>
                                </div>
                            )}
                            <div>
                                <p className="mb-1.5 text-sm font-medium">
                                    {t('upload.destination', 'Destination')}
                                </p>
                                <CollectionTreePicker
                                    value={destination}
                                    onChange={d => setDestination(d)}
                                    requireCapability="createAsset"
                                    allowCreate
                                />
                            </div>
                            <LabeledControl
                                label={t(
                                    'upload.quiet',
                                    'Quiet (no notification, no webhook)'
                                )}
                            >
                                <Switch
                                    checked={quiet}
                                    onCheckedChange={setQuiet}
                                />
                            </LabeledControl>
                        </div>

                        <div className="space-y-4">
                            {workspaceId &&
                            templates.data &&
                            templates.data.items.length > 0 ? (
                                <AssetDataTemplateSelect
                                    templates={templates.data.items}
                                    value={templateIds}
                                    onChange={setTemplateIds}
                                    onApply={tpl => {
                                        if (
                                            tpl.privacy !== undefined &&
                                            tpl.privacy !== null
                                        ) {
                                            setPrivacy(tpl.privacy);
                                        }
                                        if (tpl.tags) {
                                            setTags(prev => [
                                                ...new Set([
                                                    ...prev,
                                                    ...(tpl.tags as Tag[]).map(
                                                        tg => tg.id
                                                    ),
                                                ]),
                                            ]);
                                        }
                                        if (tpl.attributes) {
                                            assetEditor.setAttributes(prev => {
                                                const next = {...prev};
                                                (
                                                    tpl.attributes as any[]
                                                ).forEach(a => {
                                                    const defId =
                                                        a.definition?.id ??
                                                        a.definitionId;
                                                    if (
                                                        !defId ||
                                                        !assetEditor
                                                            .definitions[defId]
                                                    ) {
                                                        return;
                                                    }
                                                    const locale =
                                                        a.locale ?? '_';
                                                    const v = {
                                                        id: `new-${uniqueId()}`,
                                                        value: a.value,
                                                    };
                                                    next[defId] = {
                                                        ...(next[defId] ?? {}),
                                                        [locale]: assetEditor
                                                            .definitions[defId]
                                                            .multiple
                                                            ? [
                                                                  ...(((next[
                                                                      defId
                                                                  ]?.[
                                                                      locale
                                                                  ] as any[]) ??
                                                                      []) as any[]),
                                                                  v,
                                                              ]
                                                            : v,
                                                    };
                                                });

                                                return next;
                                            });
                                        }
                                    }}
                                />
                            ) : null}
                            <PrivacyField
                                value={privacy}
                                onChange={setPrivacy}
                                allowUnset
                                inheritedPrivacy={
                                    privacyInfo.data?.computedPrivacy
                                }
                                disabled={
                                    privacyInfo.data
                                        ? !privacyInfo.data.canEditAssetPrivacy
                                        : false
                                }
                            />
                            <FormRow label={t('common.tags', 'Tags')}>
                                <TagSelect
                                    multiple
                                    workspaceId={workspaceId}
                                    value={tags}
                                    onChange={setTags}
                                    disabled={!workspaceId}
                                />
                            </FormRow>
                            <LabeledControl
                                label={t(
                                    'upload.as_story',
                                    'Create a story with these files'
                                )}
                            >
                                <Switch
                                    checked={isStory}
                                    onCheckedChange={setIsStory}
                                />
                            </LabeledControl>
                            {isStory ? (
                                <div className="space-y-3 rounded-md border p-3">
                                    <FormRow
                                        label={t(
                                            'upload.story_title',
                                            'Story title'
                                        )}
                                    >
                                        <Input
                                            value={storyTitle}
                                            onChange={e =>
                                                setStoryTitle(e.target.value)
                                            }
                                        />
                                    </FormRow>
                                    <FormRow
                                        label={t(
                                            'upload.story_tags',
                                            'Story tags'
                                        )}
                                    >
                                        <TagSelect
                                            multiple
                                            workspaceId={workspaceId}
                                            value={storyTags}
                                            onChange={setStoryTags}
                                            disabled={!workspaceId}
                                        />
                                    </FormRow>
                                    {workspaceId ? (
                                        <AttributesEditor
                                            attributes={storyEditor.attributes}
                                            definitions={
                                                storyEditor.definitions
                                            }
                                            onChange={storyEditor.onChange}
                                            target={AssetTypeFilter.Story}
                                            workspaceId={workspaceId}
                                            workspaceLocales={
                                                workspace?.enabledLocales
                                            }
                                        />
                                    ) : null}
                                </div>
                            ) : null}
                            <Accordion
                                type="single"
                                collapsible
                                defaultValue="attributes"
                            >
                                <AccordionItem value="attributes">
                                    <AccordionTrigger>
                                        {t('upload.attributes', 'Attributes')}
                                    </AccordionTrigger>
                                    <AccordionContent>
                                        {workspaceId ? (
                                            <AttributesEditor
                                                attributes={
                                                    assetEditor.attributes
                                                }
                                                definitions={
                                                    assetEditor.definitions
                                                }
                                                onChange={assetEditor.onChange}
                                                target={AssetTypeFilter.Asset}
                                                workspaceId={workspaceId}
                                                workspaceLocales={
                                                    workspace?.enabledLocales
                                                }
                                            />
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                {t(
                                                    'upload.select_destination_first',
                                                    'Select a destination to edit attributes.'
                                                )}
                                            </p>
                                        )}
                                    </AccordionContent>
                                </AccordionItem>
                                {workspaceId ? (
                                    <AccordionItem value="template">
                                        <AccordionTrigger>
                                            {t(
                                                'upload.save_as_template',
                                                'Save as template'
                                            )}
                                        </AccordionTrigger>
                                        <AccordionContent>
                                            <SaveAsTemplateSection
                                                workspaceId={workspaceId}
                                                collectionId={
                                                    destination?.collectionId
                                                }
                                                privacy={privacy}
                                                tags={tags}
                                                attributes={indexToCreateActions(
                                                    assetEditor.attributes,
                                                    assetEditor.definitions
                                                )}
                                                appliedTemplateId={
                                                    templateIds[0]
                                                }
                                                onSaved={() =>
                                                    templates.refetch()
                                                }
                                            />
                                        </AccordionContent>
                                    </AccordionItem>
                                ) : null}
                            </Accordion>
                        </div>
                    </div>
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={submitting}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={!canSubmit}
                        loading={submitting}
                    >
                        <UploadCloudIcon />{' '}
                        {t('upload.submit', 'Upload {{count}} file(s)', {
                            count,
                        })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function FileRow({
    file,
    lang,
    onRemove,
}: {
    file: File;
    lang: string;
    onRemove: () => void;
}) {
    const [preview, setPreview] = useState<string>();
    useEffect(() => {
        if (!file.type.startsWith('image/')) {
            return;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    return (
        <li className="flex items-center gap-2 rounded-md border bg-card px-2 py-1.5 text-sm">
            <span className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded bg-media-bg">
                {preview ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                        src={preview}
                        alt=""
                        className="size-full object-cover"
                    />
                ) : (
                    <FileIcon className="size-4 text-muted-foreground" />
                )}
            </span>
            <span className="min-w-0 flex-1 truncate" title={file.name}>
                {file.name}
            </span>
            <span className="text-xs text-muted-foreground">
                {file.type || '—'}
            </span>
            <span className="text-xs text-muted-foreground">
                {formatFileSize(file.size, true, lang)}
            </span>
            <Button
                variant="ghost"
                size="icon-xs"
                onClick={onRemove}
                aria-label="Remove"
            >
                <XIcon />
            </Button>
        </li>
    );
}

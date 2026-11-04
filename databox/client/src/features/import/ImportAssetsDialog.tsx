'use client';

import {useEffect, useState} from 'react';
import {useDropzone} from 'react-dropzone';
import {useTranslation} from 'react-i18next';
import type {TFunction} from 'i18next';
import {useRouter} from 'next/navigation';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {
    BookOpenIcon,
    ChevronDownIcon,
    FileJsonIcon,
    UploadCloudIcon,
    XIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {
    AssetImport,
    AssetImportFormat,
    CollectionPrivacyInfo,
    Privacy,
} from '@/types/api';
import {EntityName} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {
    CollectionTreePicker,
    TreeSelection,
} from '@/components/form/CollectionTreePicker';
import {PrivacyField} from '@/components/form/PrivacyField';
import {SimpleSelect} from '@/components/ui/select';
import {FormRow} from '@/components/ui/input';
import {Alert, Progress, Skeleton} from '@/components/ui/misc';
import {Button} from '@/components/ui/button';
import {CopyButton} from '@/components/ui/copy-button';
import {createAssetImport} from '@/lib/api/imports';
import {getCollectionPrivacyInfo} from '@/lib/api/collections';
import {formatFileSize} from '@/lib/utils/format';
import {iri} from '@/lib/utils/iri';
import {cn} from '@/lib/utils/cn';
import {routes} from '@/lib/routes';
import {
    detectImportFormat,
    ImportPreview,
    ImportPreviewError,
    IMPORT_PREVIEW_MAX_SIZE,
    previewImportFile,
} from './parseImportFile';
import {assetImportsQueryKey} from './queryKeys';

export const IMPORT_FILE_ACCEPT = {
    'application/json': ['.json'],
    'application/x-ndjson': ['.ndjson', '.jsonl'],
};

const exampleLine = JSON.stringify({
    key: 'photo-001',
    name: 'Sunset',
    path: 'Travel/2024',
    tags: ['beach'],
    attributes: {'description:en': 'A sunset over the sea'},
    sourceUrl: 'https://example.com/sunset.jpg',
});

type Props = ModalProps<AssetImport> & {
    workspaceId?: string;
    collectionId?: string;
};

export function previewErrorLabel(
    error: ImportPreviewError,
    t: TFunction
): string {
    switch (error.code) {
        case 'invalid_line':
            return t(
                'import.preview.error.invalid_line',
                'Invalid JSON: {{message}}',
                {
                    message: error.message,
                }
            );
        case 'not_object':
            return t(
                'import.preview.error.not_object',
                'The entry is not an object'
            );
        case 'missing_source':
            return t(
                'import.preview.error.missing_source',
                'The entry needs one of sourceUrl, previewUrl, thumbnailUrl, renditions or name'
            );
        case 'collection_and_path':
            return t(
                'import.preview.error.collection_and_path',
                '"collection" and "path" cannot be both set'
            );
    }
}

/**
 * Creates assets from a JSON or NDJSON instructions file, uploaded to the
 * storage and processed asynchronously by the API.
 */
export function ImportAssetsDialog({
    open,
    onOpenChange,
    resolve,
    workspaceId: initialWorkspaceId,
    collectionId,
}: Props) {
    const {t, i18n} = useTranslation();
    const router = useRouter();
    const queryClient = useQueryClient();
    const [file, setFile] = useState<File>();
    const [format, setFormat] = useState<AssetImportFormat>();
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
    const [privacy, setPrivacy] = useState<Privacy | undefined>();
    const [progress, setProgress] = useState<number>();

    const {getRootProps, getInputProps, isDragActive} = useDropzone({
        multiple: false,
        accept: IMPORT_FILE_ACCEPT,
        onDrop: files => {
            const picked = files[0];
            if (!picked) {
                return;
            }
            setFile(picked);
            const detected = detectImportFormat(picked.name);
            if (detected) {
                setFormat(detected);
            }
        },
    });

    const preview = useQuery<ImportPreview>({
        queryKey: [
            'asset-import-preview',
            file?.name,
            file?.size,
            file?.lastModified,
            format,
        ],
        queryFn: () => previewImportFile(file!, format!),
        enabled: !!file && !!format,
        staleTime: Infinity,
        gcTime: 0,
        retry: false,
    });

    const privacyInfo = useQuery<CollectionPrivacyInfo>({
        queryKey: ['collection-privacy', destination?.collectionId],
        queryFn: () => getCollectionPrivacyInfo(destination!.collectionId!),
        enabled: !!destination?.collectionId,
    });

    useEffect(() => {
        if (privacyInfo.data && !privacyInfo.data.canEditAssetPrivacy) {
            setPrivacy(undefined);
        }
    }, [privacyInfo.data]);

    const canSubmit =
        !!file && !!format && !!destination && !preview.data?.fatal;

    const submit = async () => {
        if (!file || !format || !destination) {
            return false;
        }
        const workspaceId = destination.workspaceId;
        try {
            const created = await createAssetImport(file, {
                workspaceId,
                collectionId: destination.collectionId,
                format,
                privacy,
                onProgress: p => setProgress(p.loaded / p.total),
            });
            void queryClient.invalidateQueries({
                queryKey: assetImportsQueryKey(workspaceId),
            });
            toast.success(t('import.created', 'Import started'), {
                description: t(
                    'import.created_help',
                    'Follow its progress in the "Imports" tab of the workspace.'
                ),
                action: {
                    label: t('import.view_imports', 'View imports'),
                    onClick: () =>
                        router.push(
                            routes.workspaceManage(workspaceId, 'imports')
                        ),
                },
            });
            resolve?.(created);
        } finally {
            setProgress(undefined);
        }
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            title={t('import.title', 'Import assets')}
            description={t(
                'import.description',
                'Create assets from a JSON or NDJSON file describing them (source URLs, attributes, tags…).'
            )}
            submitLabel={t('import.submit', 'Import')}
            canSubmit={canSubmit}
            dirty={!!file}
            bodyClassName="space-y-4"
            onSubmit={submit}
        >
            <FormRow label={t('import.file', 'Instructions file')}>
                {file ? (
                    <div
                        className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                        data-testid="import-file"
                    >
                        <FileJsonIcon className="size-4 shrink-0" />
                        <span className="min-w-0 flex-1 truncate">
                            {file.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {formatFileSize(file.size, true, i18n.language)}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            aria-label={t('common.remove', 'Remove')}
                            onClick={() => setFile(undefined)}
                        >
                            <XIcon />
                        </Button>
                    </div>
                ) : (
                    <div
                        {...getRootProps({
                            className: cn(
                                'flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border-2 border-dashed px-4 py-6 text-sm text-muted-foreground transition-colors hover:border-primary hover:bg-accent/40',
                                isDragActive && 'border-primary bg-primary/5'
                            ),
                        })}
                    >
                        <input
                            {...getInputProps()}
                            data-testid="import-file-input"
                        />
                        <UploadCloudIcon className="size-6" />
                        {t(
                            'import.drop',
                            'Drop a .json, .ndjson or .jsonl file here or click to browse'
                        )}
                    </div>
                )}
            </FormRow>

            <FormRow label={t('import.format', 'Format')}>
                <SimpleSelect<AssetImportFormat>
                    value={format}
                    onValueChange={setFormat}
                    placeholder={t(
                        'import.format_placeholder',
                        'Select a format'
                    )}
                    options={[
                        {value: 'json', label: t('import.format.json', 'JSON')},
                        {
                            value: 'ndjson',
                            label: t(
                                'import.format.ndjson',
                                'NDJSON (one entry per line)'
                            ),
                        },
                    ]}
                />
            </FormRow>

            {file && format ? (
                <ImportPreviewSummary
                    preview={preview.data}
                    loading={preview.isLoading}
                    error={preview.error}
                />
            ) : null}

            <div>
                <p className="mb-1.5 text-sm font-medium">
                    {t('import.destination', 'Destination')}
                </p>
                <CollectionTreePicker
                    value={destination}
                    onChange={setDestination}
                    requireCapability="createAsset"
                    showSelection
                />
                <p className="mt-1 text-xs text-muted-foreground">
                    {t(
                        'import.destination_help',
                        'Entries with a "collection" or a "path" are created there instead ("path" is created under the destination).'
                    )}
                </p>
            </div>

            <PrivacyField
                value={privacy}
                onChange={setPrivacy}
                allowUnset
                inheritedPrivacy={privacyInfo.data?.computedPrivacy}
                disabled={
                    privacyInfo.data
                        ? !privacyInfo.data.canEditAssetPrivacy
                        : false
                }
            />

            <ImportHelp />

            {progress !== undefined ? (
                <Progress value={Math.round(progress * 100)} />
            ) : null}
        </FormDialog>
    );
}

function ImportPreviewSummary({
    preview,
    loading,
    error,
}: {
    preview?: ImportPreview;
    loading: boolean;
    error: unknown;
}) {
    const {t, i18n} = useTranslation();

    if (loading) {
        return <Skeleton className="h-12 w-full" />;
    }
    if (error || !preview) {
        return (
            <Alert variant="warning" data-testid="import-preview">
                {t(
                    'import.preview.unreadable',
                    'The file could not be read for a preview.'
                )}
            </Alert>
        );
    }
    if (preview.fatal) {
        return (
            <Alert
                variant="destructive"
                data-testid="import-preview"
                title={
                    preview.fatal.code === 'invalid_json'
                        ? t('import.preview.invalid_json', 'Invalid JSON')
                        : t(
                              'import.preview.invalid_structure',
                              'The document must be an array of entries or an object with an "assets" array'
                          )
                }
            >
                {preview.fatal.code === 'invalid_json' ? (
                    <p className="font-mono text-xs">{preview.fatal.message}</p>
                ) : null}
            </Alert>
        );
    }

    const size = formatFileSize(IMPORT_PREVIEW_MAX_SIZE, true, i18n.language);
    const summary =
        preview.count === undefined
            ? t(
                  'import.preview.too_large',
                  'The file is larger than {{size}}: it will be checked during the import.',
                  {size}
              )
            : preview.truncated
              ? t(
                    'import.preview.count_partial',
                    'At least {{count}} entries (only the first {{size}} were read)',
                    {count: preview.count, size}
                )
              : t('import.preview.count', '{{count}} entries', {
                    count: preview.count,
                });

    return (
        <Alert
            variant={preview.errorCount > 0 ? 'warning' : 'info'}
            data-testid="import-preview"
            title={summary}
        >
            {preview.errorCount > 0 ? (
                <>
                    <p>
                        {t(
                            'import.preview.errors',
                            '{{count}} entries will be rejected:',
                            {count: preview.errorCount}
                        )}
                    </p>
                    <ul
                        className="mt-1 max-h-40 space-y-0.5 overflow-y-auto font-mono text-xs"
                        data-testid="import-preview-errors"
                    >
                        {preview.errors.map(e => (
                            <li key={e.line}>
                                {t('import.line', 'Line {{line}}', {
                                    line: e.line,
                                })}
                                {': '}
                                {previewErrorLabel(e, t)}
                            </li>
                        ))}
                        {preview.errorCount > preview.errors.length ? (
                            <li className="text-muted-foreground">
                                {t(
                                    'import.preview.more_errors',
                                    '…and {{count}} more',
                                    {
                                        count:
                                            preview.errorCount -
                                            preview.errors.length,
                                    }
                                )}
                            </li>
                        ) : null}
                    </ul>
                </>
            ) : null}
        </Alert>
    );
}

function ImportHelp() {
    const {t} = useTranslation();

    return (
        <details className="group/help min-w-0 rounded-md border text-xs">
            <summary className="flex cursor-pointer items-center gap-2 p-3 font-medium select-none">
                <BookOpenIcon className="size-4" />
                <span className="flex-1">
                    {t('import.help.title', 'File format')}
                </span>
                <ChevronDownIcon className="size-4 transition-transform group-open/help:rotate-180" />
            </summary>
            <div className="space-y-2 border-t p-3 text-muted-foreground">
                <p>
                    {t(
                        'import.help.formats',
                        'NDJSON: one JSON object per line. JSON: an array of objects, or an object with an "assets" array.'
                    )}
                </p>
                <p>
                    {t(
                        'import.help.fields',
                        'Each entry needs at least one of sourceUrl, previewUrl, thumbnailUrl, renditions or name. Optional: key, externalId, collection or path, tags, privacy, attributes ("slug" or "slug:locale"), sourceName, sourceType. Unknown fields are ignored.'
                    )}
                </p>
                <p>
                    {t(
                        'import.help.key',
                        'An entry whose key already exists in the workspace is skipped: the same file can be imported again.'
                    )}
                </p>
                <div className="flex items-center gap-1 font-medium text-foreground">
                    {t('import.help.example', 'Example line')}
                    <CopyButton value={exampleLine} />
                </div>
                <pre className="overflow-x-auto rounded bg-muted p-2 font-mono text-foreground">
                    {exampleLine}
                </pre>
            </div>
        </details>
    );
}

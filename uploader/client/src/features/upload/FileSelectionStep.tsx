'use client';

import Link from 'next/link';
import {Trans, useTranslation} from 'react-i18next';
import {
    ArrowRightIcon,
    InfoIcon,
    LinkIcon,
    PlusIcon,
    SendIcon,
    Trash2Icon,
    UploadCloudIcon,
} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {useConfirm} from '@alchemy/next-ui/ui/confirm';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@alchemy/next-ui/ui/overlays';
import {routes} from '@/lib/routes';
import {cn} from '@alchemy/next-ui/utils/cn';
import {formatFileSize} from '@/lib/utils/format';
import {describeAllowedTypes} from '@alchemy/next-ui/utils/mime';
import {FileTile} from './FileTile';
import {checkBatch, totalSize} from './limits';
import {useBatchIssueMessage, useFileDrop} from './useFileDrop';
import {useUploadLimits} from './useUploadLimits';

type Props = {
    targetId: string;
    files: File[];
    onChange: (files: File[]) => void;
    onNext: () => void;
    /** The form schema of the target is not known yet */
    nextDisabled?: boolean;
    /** A form follows; without one, the next step sends the batch */
    withForm: boolean;
    /** Offers to import a file from a URL instead */
    urlImport?: boolean;
};

export function FileSelectionStep({
    targetId,
    files,
    onChange,
    onNext,
    nextDisabled,
    withForm,
    urlImport = true,
}: Props) {
    const {t} = useTranslation();
    const upload = useUploadLimits();
    const confirm = useConfirm();
    const single = upload.maxFileCount === 1;
    const issues = checkBatch(files, upload);
    const issueMessage = useBatchIssueMessage();
    const size = totalSize(files);
    const {getRootProps, getInputProps, isDragActive, open, typeRejected} =
        useFileDrop(files, onChange);

    const removeAll = async () => {
        if (
            await confirm({
                title: t('upload.remove_all_title', 'Remove all the files'),
                description: t('upload.remove_all_confirm', {
                    defaultValue_one: 'The selected file will be removed.',
                    defaultValue_other:
                        'The {{count}} selected files will be removed.',
                    count: files.length,
                }),
                confirmLabel: t('upload.remove_all_yes', 'Remove all'),
                destructive: true,
            })
        ) {
            onChange([]);
        }
    };

    return (
        <div className="flex flex-col gap-4">
            <div
                {...getRootProps({
                    'data-testid': 'dropzone',
                    'className': cn(
                        'flex min-h-56 cursor-pointer flex-col gap-4 rounded-lg border-2 border-dashed p-4 transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring/60',
                        isDragActive
                            ? 'border-primary bg-primary/5'
                            : 'border-input hover:border-primary/60'
                    ),
                } as React.HTMLAttributes<HTMLElement>)}
            >
                <input
                    {...getInputProps({
                        'data-testid': 'file-input',
                    } as React.InputHTMLAttributes<HTMLInputElement>)}
                />
                {files.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-2 text-center text-muted-foreground">
                        <UploadCloudIcon className="size-10" />
                        <p className="font-medium text-foreground">
                            {isDragActive
                                ? t('upload.drop_here', 'Drop the files here…')
                                : single
                                  ? t(
                                        'upload.drop_single',
                                        'Drag a file here, or click to select one'
                                    )
                                  : t(
                                        'upload.drop_multiple',
                                        'Drag files here, or click to select them'
                                    )}
                        </p>
                    </div>
                ) : (
                    <ul
                        className="flex flex-wrap gap-3"
                        data-testid="file-list"
                    >
                        {files.map((file, i) => (
                            <FileTile
                                key={`${file.name}:${file.size}:${file.lastModified}:${i}`}
                                file={file}
                                onRemove={() =>
                                    onChange(files.filter((_, j) => j !== i))
                                }
                            />
                        ))}
                        <li className="flex">
                            <button
                                type="button"
                                data-testid="add-files"
                                className="flex min-h-36 w-36 flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed p-2 text-center text-xs text-muted-foreground transition-colors hover:border-primary/60 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                                onClick={e => {
                                    // The dropzone would open the picker too
                                    e.stopPropagation();
                                    open();
                                }}
                            >
                                <PlusIcon className="size-6" />
                                {single
                                    ? t(
                                          'upload.replace_file',
                                          'Choose another file'
                                      )
                                    : t('upload.add_files', 'Add files')}
                            </button>
                        </li>
                    </ul>
                )}
            </div>

            <UploadConstraints showTypes={typeRejected} />

            {files.length > 0 ? (
                <div
                    data-testid="selection-summary"
                    className="flex flex-wrap items-center justify-between gap-2 text-sm"
                >
                    <span>
                        {t('upload.selected_count', {
                            defaultValue_one: '{{count}} file selected',
                            defaultValue_other: '{{count}} files selected',
                            count: files.length,
                        })}
                        <span className="text-muted-foreground">
                            {' · '}
                            {upload.maxCommitSize
                                ? t(
                                      'upload.size_of_max',
                                      '{{size}} of {{max}}',
                                      {
                                          size: formatFileSize(size),
                                          max: formatFileSize(
                                              upload.maxCommitSize
                                          ),
                                      }
                                  )
                                : formatFileSize(size)}
                        </span>
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        data-testid="remove-all"
                        onClick={removeAll}
                    >
                        <Trash2Icon /> {t('upload.remove_all', 'Remove all')}
                    </Button>
                </div>
            ) : null}

            {issues.map(issue => (
                <Alert
                    key={issue.type}
                    variant="destructive"
                    data-testid="batch-issue"
                >
                    {issueMessage(issue)}
                </Alert>
            ))}

            <div className="flex flex-wrap items-center justify-between gap-3">
                {urlImport ? (
                    <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                        <LinkIcon className="size-4 shrink-0" />
                        <span>
                            <Trans
                                i18nKey="upload.or_import_url"
                                defaults="or <anchor>import a file from a URL</anchor>"
                                components={{
                                    anchor: (
                                        <Link
                                            href={routes.download(targetId)}
                                            data-testid="download-link"
                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                        />
                                    ),
                                }}
                            />
                        </span>
                    </span>
                ) : (
                    <span />
                )}
                <Button
                    data-testid="selection-next"
                    disabled={
                        nextDisabled || files.length === 0 || issues.length > 0
                    }
                    onClick={onNext}
                >
                    {withForm ? (
                        <>
                            {t('upload.next', 'Next')} <ArrowRightIcon />
                        </>
                    ) : (
                        <>
                            <SendIcon />{' '}
                            {files.length > 0
                                ? t('upload.send_files', {
                                      defaultValue_one: 'Send {{count}} file',
                                      defaultValue_other:
                                          'Send {{count}} files',
                                      count: files.length,
                                  })
                                : t('upload.send', 'Send')}
                        </>
                    )}
                </Button>
            </div>
        </div>
    );
}

/**
 * The limits of a batch, told before the files are chosen. The accepted types
 * are behind an info button until a file is refused for its type.
 */
function UploadConstraints({showTypes}: {showTypes: boolean}) {
    const {t} = useTranslation();
    const upload = useUploadLimits();
    const types = describeAllowedTypes(upload.allowedTypes);
    const typesLabel = types
        ? t('upload.accepted_types', 'Accepted types: {{types}}', {types})
        : undefined;
    const typesHidden = !!typesLabel && !showTypes;
    const constraints = [
        showTypes ? typesLabel : undefined,
        upload.maxFileSize
            ? t('upload.max_file_size', 'Up to {{max}} per file', {
                  max: formatFileSize(upload.maxFileSize),
              })
            : undefined,
        upload.fileCountFromLink && upload.maxFileCount
            ? t('upload.link_file_count_left', {
                  defaultValue_one: '{{count}} file left on this link',
                  defaultValue_other: '{{count}} files left on this link',
                  count: upload.maxFileCount,
              })
            : upload.maxFileCount && upload.maxFileCount > 1
              ? t('upload.max_file_count', 'Up to {{max}} files per upload', {
                    max: upload.maxFileCount,
                })
              : undefined,
        upload.maxCommitSize
            ? upload.commitSizeFromLink
                ? t('upload.link_size_left', '{{max}} left on this link', {
                      max: formatFileSize(upload.maxCommitSize),
                  })
                : t('upload.max_commit_size', 'Up to {{max}} per upload', {
                      max: formatFileSize(upload.maxCommitSize),
                  })
            : undefined,
    ].filter(Boolean);

    if (constraints.length === 0 && !typesHidden) {
        return null;
    }

    return (
        <div
            data-testid="upload-constraints"
            className="-mt-2 flex items-start justify-between gap-2 text-xs text-muted-foreground"
        >
            <p>{constraints.join(' · ')}</p>
            {typesHidden ? (
                <Popover>
                    <PopoverTrigger asChild>
                        <Button
                            type="button"
                            size="icon-xs"
                            variant="ghost"
                            className="-my-1.5 shrink-0"
                            data-testid="accepted-types-info"
                            aria-label={t(
                                'upload.accepted_types_info',
                                'Accepted file types'
                            )}
                        >
                            <InfoIcon />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent
                        align="end"
                        className="max-w-xs text-xs"
                        data-testid="accepted-types"
                    >
                        {typesLabel}
                    </PopoverContent>
                </Popover>
            ) : null}
        </div>
    );
}

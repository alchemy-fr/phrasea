'use client';

import Link from 'next/link';
import {Trans, useTranslation} from 'react-i18next';
import {
    ArrowRightIcon,
    LinkIcon,
    PlusIcon,
    SendIcon,
    Trash2Icon,
    UploadCloudIcon,
} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {useConfig} from '@/lib/config/ConfigProvider';
import {routes} from '@/lib/routes';
import {cn} from '@alchemy/next-ui/utils/cn';
import {formatFileSize} from '@/lib/utils/format';
import {describeAllowedTypes} from '@alchemy/next-ui/utils/mime';
import {FileTile} from './FileTile';
import {checkBatch, totalSize} from './limits';
import {useBatchIssueMessage, useFileDrop} from './useFileDrop';

type Props = {
    targetId: string;
    files: File[];
    onChange: (files: File[]) => void;
    onNext: () => void;
    /** The form schema of the target is not known yet */
    nextDisabled?: boolean;
    /** A form follows; without one, the next step sends the batch */
    withForm: boolean;
};

export function FileSelectionStep({
    targetId,
    files,
    onChange,
    onNext,
    nextDisabled,
    withForm,
}: Props) {
    const {t} = useTranslation();
    const {upload} = useConfig();
    const single = upload.maxFileCount === 1;
    const issues = checkBatch(files, upload);
    const issueMessage = useBatchIssueMessage();
    const size = totalSize(files);
    const {getRootProps, getInputProps, isDragActive, open} = useFileDrop(
        files,
        onChange
    );

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

            <UploadConstraints />

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
                        onClick={() => onChange([])}
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

/** The limits of a batch, told before the files are chosen */
function UploadConstraints() {
    const {t} = useTranslation();
    const {upload} = useConfig();
    const types = describeAllowedTypes(upload.allowedTypes);
    const constraints = [
        types
            ? t('upload.accepted_types', 'Accepted types: {{types}}', {types})
            : undefined,
        upload.maxFileSize
            ? t('upload.max_file_size', 'Up to {{max}} per file', {
                  max: formatFileSize(upload.maxFileSize),
              })
            : undefined,
        upload.maxFileCount && upload.maxFileCount > 1
            ? t('upload.max_file_count', 'Up to {{max}} files per upload', {
                  max: upload.maxFileCount,
              })
            : undefined,
        upload.maxCommitSize
            ? t('upload.max_commit_size', 'Up to {{max}} per upload', {
                  max: formatFileSize(upload.maxCommitSize),
              })
            : undefined,
    ].filter(Boolean);

    if (constraints.length === 0) {
        return null;
    }

    return (
        <p
            data-testid="upload-constraints"
            className="-mt-2 text-xs text-muted-foreground"
        >
            {constraints.join(' · ')}
        </p>
    );
}

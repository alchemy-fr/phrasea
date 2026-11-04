'use client';

import Link from 'next/link';
import {useDropzone, type FileRejection} from 'react-dropzone';
import {Trans, useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {ArrowRightIcon, UploadCloudIcon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Alert} from '@alchemy/next-ui/ui/misc';
import {useConfig} from '@/lib/config/ConfigProvider';
import {routes} from '@/lib/routes';
import {cn} from '@alchemy/next-ui/utils/cn';
import {formatFileSize} from '@/lib/utils/format';
import {toDropzoneAccept} from '@/lib/utils/mime';
import {FileTile} from './FileTile';
import {checkBatch, mergeSelection, totalSize, type BatchIssue} from './limits';

type Props = {
    targetId: string;
    files: File[];
    onChange: (files: File[]) => void;
    onNext: () => void;
    /** The form schema of the target is not known yet */
    nextDisabled?: boolean;
};

export function FileSelectionStep({
    targetId,
    files,
    onChange,
    onNext,
    nextDisabled,
}: Props) {
    const {t} = useTranslation();
    const {upload} = useConfig();
    const single = upload.maxFileCount === 1;
    const issues = checkBatch(files, upload);
    const size = totalSize(files);

    const reportRejections = (rejections: FileRejection[]) => {
        for (const {file, errors} of rejections) {
            const codes = errors.map(e => e.code);
            if (codes.includes('file-too-large')) {
                toast.error(
                    t(
                        'upload.file_too_large',
                        'Size of {{filename}} is higher than the maximum allowed size ({{max}})',
                        {
                            filename: file.name,
                            max: formatFileSize(upload.maxFileSize ?? 0),
                        }
                    )
                );
            } else if (codes.includes('file-invalid-type')) {
                toast.error(
                    t(
                        'upload.file_type_not_allowed',
                        '{{filename}} is not an allowed file type',
                        {filename: file.name}
                    )
                );
            } else if (!codes.includes('too-many-files')) {
                toast.error(`${file.name}: ${errors[0]?.message ?? ''}`);
            }
        }
    };

    const {getRootProps, getInputProps, isDragActive} = useDropzone({
        accept: toDropzoneAccept(upload.allowedTypes),
        maxSize: upload.maxFileSize,
        multiple: !single,
        onDrop: (accepted, rejections) => {
            reportRejections(rejections);
            if (accepted.length > 0) {
                onChange(mergeSelection(files, accepted, upload));
            }
        },
    });

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
                        <p>
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
                    </ul>
                )}
            </div>

            <div
                data-testid="selection-summary"
                className="flex flex-col gap-1 text-sm text-muted-foreground"
            >
                <span>
                    {t('upload.selected_count', {
                        defaultValue_one: '{{count}} file selected',
                        defaultValue_other: '{{count}} files selected',
                        count: files.length,
                    })}
                </span>
                <span>
                    {upload.maxCommitSize
                        ? t(
                              'upload.total_size_max',
                              'Total size: {{size}} / {{max}}',
                              {
                                  size: formatFileSize(size),
                                  max: formatFileSize(upload.maxCommitSize),
                              }
                          )
                        : t('upload.total_size', 'Total size: {{size}}', {
                              size: formatFileSize(size),
                          })}
                </span>
                {upload.maxFileSize ? (
                    <span>
                        {t(
                            'upload.max_file_size',
                            'Maximum file size: {{max}}',
                            {
                                max: formatFileSize(upload.maxFileSize),
                            }
                        )}
                    </span>
                ) : null}
            </div>

            {issues.map(issue => (
                <Alert
                    key={issue.type}
                    variant="destructive"
                    data-testid="batch-issue"
                >
                    <BatchIssueMessage issue={issue} />
                </Alert>
            ))}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <span className="text-sm text-muted-foreground">
                    <Trans
                        i18nKey="upload.or_download"
                        defaults="or just <anchor>download</anchor> URLs"
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
                <Button
                    data-testid="selection-next"
                    disabled={
                        nextDisabled || files.length === 0 || issues.length > 0
                    }
                    onClick={onNext}
                >
                    {t('upload.next', 'Next')} <ArrowRightIcon />
                </Button>
            </div>
        </div>
    );
}

function BatchIssueMessage({issue}: {issue: BatchIssue}) {
    const {t} = useTranslation();

    switch (issue.type) {
        case 'too_many_files':
            return t(
                'upload.too_many_files',
                'You selected {{count}} files, at most {{max}} can be sent at once.',
                {count: issue.count, max: issue.max}
            );
        case 'too_large_batch':
            return t(
                'upload.too_large_batch',
                'The files weigh {{size}}, at most {{max}} can be sent at once.',
                {
                    size: formatFileSize(issue.size),
                    max: formatFileSize(issue.max),
                }
            );
    }
}

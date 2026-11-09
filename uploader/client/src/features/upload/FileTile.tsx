'use client';

import {useTranslation} from 'react-i18next';
import {
    AlertCircleIcon,
    CheckCircle2Icon,
    ClockIcon,
    Loader2Icon,
    RotateCwIcon,
    WifiOffIcon,
    XIcon,
} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Tooltip} from '@alchemy/next-ui/ui/overlays';
import {FileFamilyIcon} from '@alchemy/next-ui/ui/file-icon';
import {cn} from '@alchemy/next-ui/utils/cn';
import {formatFileSize} from '@/lib/utils/format';
import {getMimeTypeFromFile} from '@alchemy/next-ui/utils/mime';
import type {UploadStatus} from './engine/UploadQueue';
import {usePreview} from './previewCache';

type Props = {
    file: File;
    /** Upload state; none while the file is only selected */
    status?: UploadStatus;
    loaded?: number;
    error?: string;
    onRemove?: () => void;
    onRetry?: () => void;
};

/**
 * A file of the batch: its preview, name and size, and once sent, its
 * progress and what went wrong.
 */
export function FileTile({
    file,
    status,
    loaded = 0,
    error,
    onRemove,
    onRetry,
}: Props) {
    const {t} = useTranslation();
    const [preview, dropPreview] = usePreview(file);
    const percent =
        status === 'done'
            ? 100
            : file.size > 0
              ? Math.min(100, Math.round((loaded / file.size) * 100))
              : 0;
    const failed = status === 'error';

    return (
        <li
            data-testid="file-tile"
            data-status={status ?? 'selected'}
            title={file.name}
            className={cn(
                'group relative flex w-36 flex-col overflow-hidden rounded-md border bg-card text-card-foreground',
                failed && 'border-destructive',
                status === 'offline' && 'border-warning'
            )}
        >
            <div className="relative flex aspect-square items-center justify-center overflow-hidden bg-muted">
                {preview ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                        src={preview}
                        alt=""
                        data-testid="file-preview"
                        className="absolute inset-0 size-full object-cover"
                        onError={dropPreview}
                    />
                ) : (
                    <FileFamilyIcon mimeType={getMimeTypeFromFile(file)} />
                )}
                {status ? (
                    // Veil shrinking from the right as the file is sent; kept
                    // once done so that it slides out instead of vanishing
                    <div
                        aria-hidden
                        data-testid="file-progress-veil"
                        className="absolute inset-y-0 right-0 bg-white opacity-70 transition-[width] duration-600 ease-[ease]"
                        style={{width: `${100 - percent}%`}}
                    />
                ) : null}
                <div className="absolute top-1 left-1 flex items-center gap-1">
                    {status === 'done' ? (
                        <CheckCircle2Icon className="size-5 rounded-full bg-background text-success" />
                    ) : status === 'queued' ? (
                        <ClockIcon
                            aria-hidden
                            className="size-5 rounded-full bg-background p-0.5 text-muted-foreground"
                        />
                    ) : status === 'uploading' || status === 'retrying' ? (
                        <Loader2Icon className="size-5 animate-spin rounded-full bg-background p-0.5 text-primary" />
                    ) : status === 'offline' ? (
                        <Tooltip content={error}>
                            <WifiOffIcon className="size-5 rounded-full bg-background p-0.5 text-warning-foreground" />
                        </Tooltip>
                    ) : failed ? (
                        <Tooltip content={error}>
                            <AlertCircleIcon
                                data-testid="file-error"
                                className="size-5 rounded-full bg-background text-destructive"
                            />
                        </Tooltip>
                    ) : null}
                </div>
                {/* The tile may sit in a dropzone, which opens the file picker on click */}
                <div className="absolute top-1 right-1 flex gap-1">
                    {onRetry && failed ? (
                        <Button
                            type="button"
                            size="icon-xs"
                            variant="secondary"
                            data-testid="file-retry"
                            aria-label={t('upload.file.retry', 'Retry')}
                            onClick={e => {
                                e.stopPropagation();
                                onRetry();
                            }}
                        >
                            <RotateCwIcon />
                        </Button>
                    ) : null}
                    {onRemove && status !== 'done' ? (
                        <Button
                            type="button"
                            size="icon-xs"
                            variant="secondary"
                            data-testid="file-remove"
                            aria-label={t(
                                'upload.file.remove',
                                'Remove {{name}}',
                                {
                                    name: file.name,
                                }
                            )}
                            onClick={e => {
                                e.stopPropagation();
                                onRemove();
                            }}
                        >
                            <XIcon />
                        </Button>
                    ) : null}
                </div>
            </div>
            <div className="flex flex-col gap-0.5 p-2 text-xs">
                <span className="truncate font-medium">{file.name}</span>
                <span className="text-muted-foreground">
                    {status === 'queued'
                        ? `${t('upload.file.queued', 'Waiting')} · ${formatFileSize(file.size)}`
                        : status && status !== 'done'
                          ? `${percent}% · ${formatFileSize(file.size)}`
                          : formatFileSize(file.size)}
                </span>
                {failed && error ? (
                    <span className="line-clamp-2 text-destructive">
                        {error}
                    </span>
                ) : null}
            </div>
        </li>
    );
}

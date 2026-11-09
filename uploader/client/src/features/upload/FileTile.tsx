'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    AlertCircleIcon,
    CheckCircle2Icon,
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
import {
    FileKind,
    getFileKind,
    getMimeTypeFromFile,
} from '@alchemy/next-ui/utils/mime';
import type {UploadStatus} from './engine/UploadQueue';

/** Images larger than this are not previewed: decoding them costs too much */
export const PREVIEW_MAX_SIZE = 15 * 1024 * 1024;

function useImagePreview(file: File): string | undefined {
    const previewable =
        getFileKind(getMimeTypeFromFile(file)) === FileKind.Image &&
        file.size < PREVIEW_MAX_SIZE;
    const [url, setUrl] = useState<string>();

    useEffect(() => {
        if (!previewable) {
            return;
        }
        const objectUrl = URL.createObjectURL(file);
        setUrl(objectUrl);

        return () => {
            URL.revokeObjectURL(objectUrl);
            setUrl(undefined);
        };
    }, [file, previewable]);

    return url;
}

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
    const preview = useImagePreview(file);
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
            <div className="relative flex aspect-square items-center justify-center bg-muted">
                {preview ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                        src={preview}
                        alt=""
                        className="size-full object-cover"
                    />
                ) : (
                    <FileFamilyIcon mimeType={getMimeTypeFromFile(file)} />
                )}
                {status && status !== 'done' ? (
                    // Veil shrinking from the right as the file is sent
                    <div
                        aria-hidden
                        className="absolute inset-y-0 right-0 bg-background/70 transition-[width] duration-300"
                        style={{width: `${100 - percent}%`}}
                    />
                ) : null}
                <div className="absolute top-1 left-1 flex items-center gap-1">
                    {status === 'done' ? (
                        <CheckCircle2Icon className="size-5 rounded-full bg-background text-success" />
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
                <div className="absolute top-1 right-1 flex gap-1">
                    {onRetry && failed ? (
                        <Button
                            type="button"
                            size="icon-xs"
                            variant="secondary"
                            data-testid="file-retry"
                            aria-label={t('upload.file.retry', 'Retry')}
                            onClick={onRetry}
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
                            onClick={onRemove}
                        >
                            <XIcon />
                        </Button>
                    ) : null}
                </div>
            </div>
            <div className="flex flex-col gap-0.5 p-2 text-xs">
                <span className="truncate font-medium">{file.name}</span>
                <span className="text-muted-foreground">
                    {status && status !== 'done'
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

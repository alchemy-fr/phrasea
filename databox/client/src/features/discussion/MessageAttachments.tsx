'use client';

import {useTranslation} from 'react-i18next';
import {
    AlertCircleIcon,
    DownloadIcon,
    FileIcon,
    ShapesIcon,
    XIcon,
} from 'lucide-react';
import type {AssetAnnotation, MessageAttachment} from '@/types/api';
import {Tooltip} from '@/components/ui/overlays';
import {formatFileSize} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';
import {getAnnotationLabel} from '@/features/annotations/annotationNames';
import {
    getAnnotationAttachments,
    getFileAttachments,
    isImage,
    type FileAttachment,
} from './messageAttachments';
import type {PendingAttachment} from './usePendingAttachments';

const removeButtonClass =
    'absolute -top-1.5 -right-1.5 rounded-full border bg-background p-0.5 text-muted-foreground opacity-0 shadow-sm group-hover/att:opacity-100 hover:text-destructive focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none';

/**
 * Attachments of a posted message: files (image thumbnails, name + download
 * otherwise) and annotations (shown on the asset when clicked). `onRemove`
 * offers to remove each of them (who may edit the message).
 */
export function PostedAttachments({
    attachments,
    onRemove,
    onAnnotationClick,
    onRemoveAnnotation,
}: {
    attachments?: MessageAttachment[] | null;
    onRemove?: (file: FileAttachment) => void;
    /** Shows the annotation (among the ones of the message) on the asset */
    onAnnotationClick?: (
        annotation: AssetAnnotation,
        annotations: AssetAnnotation[]
    ) => void;
    onRemoveAnnotation?: (annotation: AssetAnnotation) => void;
}) {
    const {t, i18n} = useTranslation();
    const files = getFileAttachments(attachments);
    const annotations = getAnnotationAttachments(attachments);
    if (files.length === 0 && annotations.length === 0) {
        return null;
    }

    return (
        <ul className="mt-1.5 flex flex-wrap gap-2">
            {annotations.map((a, i) => {
                const label = getAnnotationLabel(t, a);

                return (
                    <li
                        key={a.id ?? i}
                        data-testid="message-annotation"
                        className="group/att relative max-w-full"
                    >
                        <AnnotationChip
                            annotation={a}
                            label={label}
                            onClick={
                                onAnnotationClick
                                    ? () => onAnnotationClick(a, annotations)
                                    : undefined
                            }
                        />
                        {onRemoveAnnotation && a.id ? (
                            <Tooltip
                                content={t(
                                    'discussion.attachment_remove',
                                    'Remove {{name}}',
                                    {name: label}
                                )}
                            >
                                <button
                                    type="button"
                                    data-testid="message-annotation-remove"
                                    onClick={() => onRemoveAnnotation(a)}
                                    aria-label={t(
                                        'discussion.attachment_remove',
                                        'Remove {{name}}',
                                        {name: label}
                                    )}
                                    className={removeButtonClass}
                                >
                                    <XIcon className="size-3" />
                                </button>
                            </Tooltip>
                        ) : null}
                    </li>
                );
            })}
            {files.map((f, i) => {
                const label = f.name || t('discussion.attachment', 'File');

                return (
                    <li
                        key={f.id ?? i}
                        data-testid="message-attachment"
                        className="group/att relative max-w-full"
                    >
                        {f.url && isImage(f) ? (
                            <a
                                href={f.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                title={label}
                                className="block overflow-hidden rounded-md border bg-muted"
                            >
                                {/* eslint-disable-next-line @next/next/no-img-element -- signed storage URL */}
                                <img
                                    src={f.url}
                                    alt={label}
                                    loading="lazy"
                                    className="max-h-40 max-w-60 object-contain"
                                />
                            </a>
                        ) : (
                            <a
                                href={f.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                download={f.name || undefined}
                                aria-disabled={!f.url}
                                className={cn(
                                    'flex max-w-72 items-center gap-2 rounded-md border bg-card px-2 py-1.5 text-xs transition-colors hover:bg-accent',
                                    !f.url && 'pointer-events-none opacity-60'
                                )}
                            >
                                <FileIcon className="size-4 shrink-0 text-muted-foreground" />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium">
                                        {label}
                                    </span>
                                    {f.size ? (
                                        <span className="text-muted-foreground">
                                            {formatFileSize(
                                                f.size,
                                                true,
                                                i18n.language
                                            )}
                                        </span>
                                    ) : null}
                                </span>
                                {f.url ? (
                                    <DownloadIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                ) : null}
                            </a>
                        )}
                        {onRemove && f.id ? (
                            <Tooltip
                                content={t(
                                    'discussion.attachment_remove',
                                    'Remove {{name}}',
                                    {name: label}
                                )}
                            >
                                <button
                                    type="button"
                                    data-testid="message-attachment-remove"
                                    onClick={() => onRemove(f)}
                                    aria-label={t(
                                        'discussion.attachment_remove',
                                        'Remove {{name}}',
                                        {name: label}
                                    )}
                                    className={removeButtonClass}
                                >
                                    <XIcon className="size-3" />
                                </button>
                            </Tooltip>
                        ) : null}
                    </li>
                );
            })}
        </ul>
    );
}

/** A shape of a message, as a chip; its color as a dot */
function AnnotationChip({
    annotation,
    label,
    onClick,
    selected,
}: {
    annotation: AssetAnnotation;
    label: string;
    onClick?: () => void;
    selected?: boolean;
}) {
    const {t} = useTranslation();
    const color = typeof annotation.c === 'string' ? annotation.c : undefined;

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={!onClick}
            title={
                onClick
                    ? t('discussion.annotation_show', 'Show on the asset')
                    : undefined
            }
            className={cn(
                'flex max-w-72 items-center gap-1.5 rounded-md border bg-card px-2 py-1 text-xs transition-colors',
                onClick && 'hover:bg-accent',
                selected && 'border-primary ring-1 ring-primary/40'
            )}
        >
            <ShapesIcon
                className="size-3.5 shrink-0"
                style={color ? {color} : undefined}
            />
            <span className="truncate">{label}</span>
        </button>
    );
}

/**
 * Shapes drawn for the message being written: clicking one shows it on the
 * asset, the cross removes it.
 */
export function DraftAnnotationList({
    annotations,
    selectedId,
    onSelect,
    onRemove,
}: {
    annotations: AssetAnnotation[];
    selectedId?: string;
    onSelect: (annotation: AssetAnnotation) => void;
    onRemove: (annotation: AssetAnnotation) => void;
}) {
    const {t} = useTranslation();
    if (annotations.length === 0) {
        return null;
    }

    return (
        <ul
            className="flex flex-wrap gap-2 px-3 pb-2"
            aria-label={t('discussion.annotations', 'Annotations')}
        >
            {annotations.map((a, i) => {
                const label = getAnnotationLabel(t, a);

                return (
                    <li
                        key={a.id ?? i}
                        data-testid="draft-annotation"
                        className="group/att relative"
                    >
                        <AnnotationChip
                            annotation={a}
                            label={label}
                            selected={!!a.id && a.id === selectedId}
                            onClick={() => onSelect(a)}
                        />
                        <button
                            type="button"
                            onClick={() => onRemove(a)}
                            aria-label={t(
                                'discussion.attachment_remove',
                                'Remove {{name}}',
                                {name: label}
                            )}
                            className={removeButtonClass}
                        >
                            <XIcon className="size-3" />
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}

/** Files being attached to the message written, with their upload progress. */
export function PendingAttachmentList({
    items,
    onRemove,
}: {
    items: PendingAttachment[];
    onRemove: (key: string) => void;
}) {
    const {t, i18n} = useTranslation();
    if (items.length === 0) {
        return null;
    }

    return (
        <ul
            className="flex flex-wrap gap-2 px-3 pb-2"
            aria-label={t('discussion.attachments', 'Attachments')}
        >
            {items.map(item => (
                <li
                    key={item.key}
                    className={cn(
                        'group/att relative flex h-12 max-w-56 items-center gap-2 overflow-hidden rounded-md border bg-card pr-7 text-xs',
                        item.status === 'error' && 'border-destructive'
                    )}
                >
                    {item.preview ? (
                        // eslint-disable-next-line @next/next/no-img-element -- local object URL
                        <img
                            src={item.preview}
                            alt=""
                            className="h-full w-12 shrink-0 object-cover"
                        />
                    ) : (
                        <span className="flex h-full w-10 shrink-0 items-center justify-center bg-muted">
                            {item.status === 'error' ? (
                                <AlertCircleIcon className="size-4 text-destructive" />
                            ) : (
                                <FileIcon className="size-4 text-muted-foreground" />
                            )}
                        </span>
                    )}
                    <span className="min-w-0 py-1">
                        <span className="block truncate font-medium">
                            {item.name}
                        </span>
                        {item.status === 'error' ? (
                            <Tooltip content={item.error}>
                                <span className="block truncate text-destructive">
                                    {t(
                                        'discussion.attachment_failed',
                                        'Upload failed'
                                    )}
                                </span>
                            </Tooltip>
                        ) : (
                            <span className="block text-muted-foreground">
                                {item.status === 'uploading'
                                    ? `${Math.round(item.progress * 100)} %`
                                    : formatFileSize(
                                          item.size,
                                          true,
                                          i18n.language
                                      )}
                            </span>
                        )}
                    </span>
                    {item.status === 'uploading' ? (
                        <span
                            className="absolute inset-x-0 bottom-0 h-0.5 bg-primary transition-[width]"
                            style={{width: `${item.progress * 100}%`}}
                            role="progressbar"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={Math.round(item.progress * 100)}
                        />
                    ) : null}
                    <button
                        type="button"
                        onClick={() => onRemove(item.key)}
                        aria-label={t(
                            'discussion.attachment_remove',
                            'Remove {{name}}',
                            {name: item.name}
                        )}
                        className="absolute top-1 right-1 rounded-full bg-background/80 p-0.5 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                    >
                        <XIcon className="size-3" />
                    </button>
                </li>
            ))}
        </ul>
    );
}

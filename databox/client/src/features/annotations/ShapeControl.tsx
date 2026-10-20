'use client';

import {useLayoutEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {CopyIcon, GripVerticalIcon, PencilIcon, Trash2Icon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {Input} from '@/components/ui/input';
import {annotationZIndex} from './controls';
import type {AssetAnnotation} from './types';
import type {Box, MediaSize} from './geometry';
import {getAnnotationLabel} from './annotationNames';

/** Space between the panel and the shape, in screen pixels */
const gap = 8;

/**
 * Floating panel of the selected shape, above it: a grip to drag the shape,
 * its name (renamed on click when editable), edit text, duplicate and
 * delete.
 */
export function ShapeControl({
    annotation,
    box,
    media,
    zoom,
    editable,
    onDragStart,
    onDelete,
    onDuplicate,
    onRename,
    onEditText,
}: {
    annotation: AssetAnnotation;
    /** Bounding box of the shape, in pixels of the media */
    box: Box;
    media: MediaSize;
    zoom: number;
    editable: boolean;
    /** The grip is pressed: the layer takes over the drag of the shape */
    onDragStart: (e: React.PointerEvent) => void;
    onDelete: () => void;
    onDuplicate: () => void;
    onRename: (name: string) => void;
    /** Text shapes: opens the inline editor */
    onEditText?: () => void;
}) {
    const {t} = useTranslation();
    const ref = useRef<HTMLDivElement>(null);
    const [name, setName] = useState<string>();
    const [size, setSize] = useState({w: 0, h: 0});
    const stop = (e: React.SyntheticEvent) => e.stopPropagation();

    useLayoutEffect(() => {
        const el = ref.current;
        if (el) {
            setSize({w: el.offsetWidth, h: el.offsetHeight});
        }
    }, [annotation, name]);

    // The panel keeps its screen size: positioned in media pixels, scaled
    // back by the zoom
    const unscale = 1 / zoom;
    const w = size.w * unscale;
    const h = size.h * unscale;
    let left = box.x;
    let top = box.y - h - gap * unscale;
    if (top < 0) {
        top = box.y + box.h + gap * unscale;
    }
    if (left + w > media.width) {
        left = Math.max(0, media.width - w);
    }

    const label = getAnnotationLabel(t, annotation);

    return (
        <div
            ref={ref}
            data-annotation-control
            data-editable={editable ? 'true' : 'false'}
            style={{
                position: 'absolute',
                top,
                left,
                zIndex: annotationZIndex + 2,
                userSelect: 'none',
                transformOrigin: 'top left',
                transform: `scale(${unscale})`,
                pointerEvents: 'auto',
                visibility: size.w ? 'visible' : 'hidden',
            }}
            onPointerDown={stop}
            onMouseDown={stop}
            onDoubleClick={stop}
            onClick={stop}
        >
            <div className="flex items-center gap-0.5 rounded-md border bg-background/95 p-0.5 text-xs whitespace-nowrap shadow-md">
                {editable ? (
                    <Tooltip content={t('annotation.move', 'Move')}>
                        <button
                            type="button"
                            data-shape-grip
                            aria-label={t('annotation.move', 'Move')}
                            className="flex h-6 w-4 cursor-grab touch-none items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-accent-foreground active:cursor-grabbing"
                            onPointerDown={e => {
                                e.stopPropagation();
                                onDragStart(e);
                            }}
                        >
                            <GripVerticalIcon className="size-3.5" />
                        </button>
                    </Tooltip>
                ) : null}
                {name !== undefined ? (
                    <Input
                        autoFocus
                        required
                        value={name}
                        aria-label={t('annotation.name', 'Name')}
                        aria-invalid={name.trim().length === 0}
                        className="h-6 w-40 px-1 text-xs"
                        onChange={e => setName(e.target.value)}
                        onKeyDown={e => {
                            e.stopPropagation();
                            if (e.key === 'Enter' && name.trim()) {
                                e.preventDefault();
                                onRename(name.trim());
                                setName(undefined);
                            } else if (e.key === 'Escape') {
                                setName(undefined);
                            }
                        }}
                        onBlur={() => setName(undefined)}
                    />
                ) : (
                    <Tooltip
                        content={t('annotation.rename', 'Rename')}
                        disabled={!editable}
                    >
                        <button
                            type="button"
                            data-shape-name
                            className="max-w-48 truncate rounded px-1.5 py-0.5 text-left hover:bg-accent disabled:pointer-events-none"
                            disabled={!editable}
                            onClick={() => setName(annotation.name ?? label)}
                        >
                            {label}
                        </button>
                    </Tooltip>
                )}
                {editable ? (
                    <>
                        {onEditText ? (
                            <Tooltip
                                content={t('annotation.edit_text', 'Edit text')}
                            >
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={onEditText}
                                    aria-label={t(
                                        'annotation.edit_text',
                                        'Edit text'
                                    )}
                                >
                                    <PencilIcon />
                                </Button>
                            </Tooltip>
                        ) : null}
                        <Tooltip
                            content={t('annotation.duplicate', 'Duplicate')}
                        >
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                onClick={onDuplicate}
                                aria-label={t(
                                    'annotation.duplicate',
                                    'Duplicate'
                                )}
                            >
                                <CopyIcon />
                            </Button>
                        </Tooltip>
                        <Tooltip content={t('common.delete', 'Delete')}>
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                className="text-destructive hover:text-destructive"
                                onClick={onDelete}
                                aria-label={t('common.delete', 'Delete')}
                            >
                                <Trash2Icon />
                            </Button>
                        </Tooltip>
                    </>
                ) : null}
            </div>
        </div>
    );
}

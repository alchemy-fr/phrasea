'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {CopyIcon, Trash2Icon} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {Input} from '@/components/ui/input';
import {annotationZIndex} from './controls';
import type {ShapeControlRef} from './types';

/**
 * Floating panel of the selected shape: its name (renamed on click when
 * editable), duplicate and delete. Positioned by the renderer, which also
 * sets `data-editable` and fills `[data-shape-name]`.
 */
export function ShapeControl({
    elementRef,
    onDelete,
    onRename,
    onDuplicate,
}: {
    elementRef: ShapeControlRef;
    onDelete: () => void;
    onDuplicate: () => void;
    onRename: (newName: string) => void;
}) {
    const {t} = useTranslation();
    const [name, setName] = useState<string>();
    const stop = (e: React.SyntheticEvent) => e.stopPropagation();

    return (
        <div
            ref={elementRef}
            data-annotation-control
            style={{
                position: 'absolute',
                top: 0,
                left: 0,
                zIndex: annotationZIndex + 2,
                userSelect: 'none',
                display: 'none',
                transformOrigin: 'top left',
                pointerEvents: 'auto',
            }}
            onPointerDown={stop}
            onMouseDown={stop}
            onDoubleClick={stop}
            onClick={stop}
            className="group/shape"
        >
            <div className="flex items-center gap-0.5 rounded-md border bg-background/95 p-0.5 text-xs whitespace-nowrap shadow-md">
                <span className="hidden items-center group-data-[editable=true]/shape:flex">
                    <Tooltip content={t('annotation.duplicate', 'Duplicate')}>
                        <Button
                            variant="ghost"
                            size="icon-xs"
                            onClick={onDuplicate}
                            aria-label={t('annotation.duplicate', 'Duplicate')}
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
                </span>
                <span className="px-1.5">
                    <span
                        data-shape-name
                        className="cursor-default group-data-[editable=true]/shape:cursor-text"
                        style={{
                            display: name === undefined ? 'inline' : 'none',
                        }}
                        onClick={e => {
                            if (
                                elementRef.current?.getAttribute(
                                    'data-editable'
                                ) !== 'true'
                            ) {
                                return;
                            }
                            setName(
                                (e.target as HTMLElement).textContent ?? ''
                            );
                        }}
                    />
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
                    ) : null}
                </span>
            </div>
        </div>
    );
}

'use client';

import {useTranslation} from 'react-i18next';
import {BanIcon, CheckIcon, FolderIcon} from 'lucide-react';
import type {Asset} from '@/types/api';
import {FileKindIcon} from '@/components/chips';
import {assetKey} from '@/features/assets/list/SelectionProvider';
import {cn} from '@/lib/utils/cn';
import {useDragOver, useDragPayload} from './DragContext';
import {type DropOp, isNeutralTarget, targetName} from './types';

/**
 * What follows the pointer during a drag: the dragged items, and — over a
 * target — what dropping would do, or why it cannot be done.
 */
export function DragGhost() {
    const {t} = useTranslation();
    const payload = useDragPayload();
    const {target, verdict} = useDragOver();
    if (!payload) {
        return null;
    }
    // A story (or collection) without a resolved name still needs a label
    const name =
        target && !isNeutralTarget(target)
            ? targetName(target) || t('dnd.untitled', 'untitled')
            : '';

    let hint: string;
    if (verdict?.ok) {
        hint = opLabel(t, verdict.op, name);
    } else if (verdict) {
        hint = reasonLabel(t, verdict.reason);
    } else if (payload.type === 'assets') {
        hint = t('dnd.hint', 'Shift: move · Ctrl: duplicate');
    } else {
        hint = t('dnd.hint_collection', 'Drop on a collection or a workspace');
    }

    return (
        <div
            data-testid="drag-ghost"
            className="pointer-events-none flex w-fit max-w-72 items-center gap-2 rounded-lg border bg-popover px-2 py-1.5 text-popover-foreground shadow-lg select-none"
        >
            {payload.type === 'assets' ? (
                <ThumbStack assets={payload.assets} />
            ) : (
                <FolderIcon className="size-5 shrink-0 text-muted-foreground" />
            )}
            <div className="min-w-0">
                <div className="truncate text-sm font-medium">
                    {payload.type === 'assets'
                        ? t('dnd.ghost.assets', '{{count}} assets', {
                              count: payload.assets.length,
                          })
                        : (payload.collection.displayName ??
                          payload.collection.name)}
                </div>
                <div
                    className={cn(
                        'flex items-center gap-1 truncate text-xs',
                        verdict?.ok
                            ? 'text-primary'
                            : verdict
                              ? 'text-destructive'
                              : 'text-muted-foreground'
                    )}
                >
                    {verdict?.ok ? (
                        <CheckIcon className="size-3 shrink-0" />
                    ) : verdict ? (
                        <BanIcon className="size-3 shrink-0" />
                    ) : null}
                    <span className="truncate">{hint}</span>
                </div>
            </div>
        </div>
    );
}

function ThumbStack({assets}: {assets: Asset[]}) {
    const shown = assets.slice(0, 3);

    return (
        <div
            className="relative shrink-0"
            style={{width: 36 + (shown.length - 1) * 6, height: 36}}
        >
            {shown.map((asset, i) => {
                const url = asset.thumbnail?.file?.url;

                return (
                    <div
                        key={assetKey(asset)}
                        className="absolute top-0 flex size-9 items-center justify-center overflow-hidden rounded border bg-media-bg"
                        style={{left: i * 6, zIndex: shown.length - i}}
                    >
                        {url ? (
                            // eslint-disable-next-line @next/next/no-img-element
                            <img
                                src={url}
                                alt=""
                                className="size-full object-cover"
                                draggable={false}
                            />
                        ) : (
                            <FileKindIcon
                                mimeType={asset.source?.type}
                                className="size-4 text-muted-foreground"
                            />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

type Translate = (
    key: string,
    defaultValue: string,
    options?: Record<string, unknown>
) => string;

function opLabel(t: Translate, op: DropOp, name: string): string {
    switch (op) {
        case 'add':
            return t('dnd.op.add', 'Add to {{name}}', {name});
        case 'move':
            return t('dnd.op.move', 'Move to {{name}}', {name});
        case 'copy':
            return t('dnd.op.copy', 'Duplicate in {{name}}', {name});
        case 'move-collection':
            return t('dnd.op.move_collection', 'Move into {{name}}', {name});
    }
}

function reasonLabel(t: Translate, reason: string): string {
    switch (reason) {
        case 'workspace':
            return t('dnd.invalid.workspace', 'Not the same workspace');
        case 'capability':
            return t('dnd.invalid.capability', 'Not allowed here');
        case 'self':
            return t('dnd.invalid.self', 'Cannot be added to itself');
        case 'descendant':
            return t(
                'dnd.invalid.descendant',
                'Cannot be moved into its own sub-collection'
            );
        case 'already':
            return t('dnd.invalid.already', 'Already there');
        case 'deleted':
            return t('dnd.invalid.deleted', 'Deleted or archived');
        default:
            return t('dnd.invalid.type', 'Cannot be dropped here');
    }
}

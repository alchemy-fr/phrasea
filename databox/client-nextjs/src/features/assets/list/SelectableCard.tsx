'use client';

import type {ComponentProps, MouseEvent, PointerEvent} from 'react';
import type {Asset} from '@/types/api';
import {useIsAssetSelected, useSelectionActions} from './SelectionProvider';
import {useAssetDrag} from '@/features/dnd/useAssetDrag';
import {cn} from '@/lib/utils/cn';
import {mergeRefs} from '@/lib/utils/refs';

type Props = Omit<ComponentProps<'div'>, 'onClick' | 'onDoubleClick'> & {
    asset: Asset;
    onItemClick: (asset: Asset, e: MouseEvent) => void;
    onItemDoubleClick: (asset: Asset) => void;
};

/**
 * Root element of a list item, and the only part of it subscribed to the
 * selection: on a selection change (a click, select all, escape...) this
 * component alone re-renders. Its children are created by the memoized item
 * above it, so React reuses them untouched — the thumbnail, the context menu,
 * the attribute list... never render again for a selection change.
 *
 * Accepts the props merged in by a Radix `asChild` trigger.
 *
 * Also the drag source of the item (see `useAssetDrag`): the drag only
 * starts after the pointer moved, a click is still a click.
 */
export function SelectableCard({
    asset,
    className,
    onItemClick,
    onItemDoubleClick,
    children,
    ref,
    onPointerDown,
    ...rest
}: Props) {
    const selected = useIsAssetSelected(asset.id);
    const disabled = useSelectionActions().disabledIds?.has(asset.id);
    const drag = useAssetDrag(asset);

    return (
        <div
            {...rest}
            ref={mergeRefs(ref, drag.setNodeRef)}
            data-asset-id={asset.id}
            data-testid="asset-item"
            data-selected={selected ? 'true' : undefined}
            data-dragging={drag.isDragging ? 'true' : undefined}
            className={cn(
                className,
                selected && 'border-primary ring-2 ring-primary/40',
                disabled && 'opacity-40',
                drag.isDragging && 'opacity-50'
            )}
            onPointerDown={(e: PointerEvent<HTMLDivElement>) => {
                onPointerDown?.(e);
                drag.listeners?.onPointerDown?.(e);
            }}
            onClick={e => !disabled && onItemClick(asset, e)}
            onDoubleClick={() => onItemDoubleClick(asset)}
        >
            {children}
        </div>
    );
}

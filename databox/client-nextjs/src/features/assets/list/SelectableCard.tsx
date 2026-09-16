'use client';

import type {ComponentProps, MouseEvent} from 'react';
import type {Asset} from '@/types/api';
import {useIsAssetSelected, useSelectionActions} from './SelectionProvider';
import {cn} from '@/lib/utils/cn';

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
 */
export function SelectableCard({
    asset,
    className,
    onItemClick,
    onItemDoubleClick,
    children,
    ...rest
}: Props) {
    const selected = useIsAssetSelected(asset.id);
    const disabled = useSelectionActions().disabledIds?.has(asset.id);

    return (
        <div
            {...rest}
            data-asset-id={asset.id}
            data-testid="asset-item"
            data-selected={selected ? 'true' : undefined}
            className={cn(
                className,
                selected && 'border-primary ring-2 ring-primary/40',
                disabled && 'opacity-40'
            )}
            onClick={e => !disabled && onItemClick(asset, e)}
            onDoubleClick={() => onItemDoubleClick(asset)}
        >
            {children}
        </div>
    );
}

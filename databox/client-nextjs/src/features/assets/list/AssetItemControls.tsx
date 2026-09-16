'use client';

import {ReactNode} from 'react';
import {CheckIcon, MoreVerticalIcon} from 'lucide-react';
import type {Asset} from '@/types/api';
import {Button} from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {useIsAssetSelected, useSelectionActions} from './SelectionProvider';
import {AssetMenuItems} from './AssetContextMenu';
import {cn} from '@/lib/utils/cn';

/**
 * Hover controls shown over a thumbnail: selection checkbox and actions menu.
 */
export function AssetItemControls({
    asset,
    actions,
}: {
    asset: Asset;
    actions?: ReactNode;
}) {
    return (
        <>
            <SelectionCheckbox asset={asset} />
            <div
                className="absolute top-1.5 right-1.5 z-10 flex items-center gap-1 opacity-0 transition-opacity group-hover/item:opacity-100 has-[[data-state=open]]:opacity-100"
                onClick={e => e.stopPropagation()}
                onDoubleClick={e => e.stopPropagation()}
            >
                {actions}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="secondary"
                            size="icon-xs"
                            className="bg-background/90 shadow-sm"
                            aria-label="Actions"
                        >
                            <MoreVerticalIcon />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <AssetMenuItems asset={asset} variant="dropdown" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </>
    );
}

/**
 * Subscribed on its own so that a selection change renders only this box.
 * A plain button rather than the Radix `Checkbox` (its presence machinery
 * costs more than everything else when hundreds of items toggle at once).
 */
function SelectionCheckbox({asset}: {asset: Asset}) {
    const selection = useSelectionActions();
    const selected = useIsAssetSelected(asset.id);

    return (
        <div
            className={cn(
                'absolute top-1.5 left-1.5 z-10 rounded bg-background/90 p-1 shadow-sm transition-opacity',
                selected
                    ? 'opacity-100'
                    : 'opacity-0 group-hover/item:opacity-100'
            )}
            onClick={e => e.stopPropagation()}
            onDoubleClick={e => e.stopPropagation()}
        >
            <button
                type="button"
                role="checkbox"
                aria-checked={selected}
                aria-label="Select"
                data-state={selected ? 'checked' : 'unchecked'}
                onClick={() => selection.toggle(asset)}
                className="flex size-4 shrink-0 items-center justify-center rounded-[4px] border border-input shadow-xs outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/60 data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground"
            >
                {selected ? <CheckIcon className="size-3.5" /> : null}
            </button>
        </div>
    );
}

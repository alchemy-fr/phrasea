'use client';

import {ReactNode} from 'react';
import {MoreVerticalIcon} from 'lucide-react';
import type {Asset} from '@/types/api';
import {Checkbox} from '@/components/ui/controls';
import {Button} from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {useSelection} from './SelectionProvider';
import {AssetMenuItems} from './AssetContextMenu';
import {cn} from '@/lib/utils/cn';

/**
 * Hover controls shown over a thumbnail: selection checkbox and actions menu.
 */
export function AssetItemControls({
    asset,
    selected,
    actions,
}: {
    asset: Asset;
    selected: boolean;
    actions?: ReactNode;
}) {
    const selection = useSelection();

    return (
        <>
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
                <Checkbox
                    checked={selected}
                    onCheckedChange={() => selection.toggle(asset)}
                    aria-label="Select"
                />
            </div>
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

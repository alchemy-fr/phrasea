'use client';

import {Fragment, PropsWithChildren, useCallback, useState} from 'react';
import type {Asset} from '@/types/api';
import {
    assetKey,
    selectionTargets,
    useSelectionActions,
} from './SelectionProvider';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
    DropdownMenuItem,
    DropdownMenuSeparator,
    MenuEmpty,
} from '@/components/ui/menu';
import {
    useAssetActions,
    type ActionContext,
    type AssetAction,
} from '@/features/assets/actions/useAssetActions';

/**
 * The assets a menu opened from a list item acts on: a selected item stands
 * for the whole selection; an unselected one gets selected alone, as a plain
 * click would, and the menu targets it only.
 *
 * Reads the selection on open only, through the stable actions context: one
 * instance per item, none of them re-renders on a selection change.
 */
export function useSelectionMenuTargets(asset: Asset): {
    targets: Asset[];
    onOpenChange: (open: boolean) => void;
} {
    const selection = useSelectionActions();
    const [targets, setTargets] = useState<Asset[]>([asset]);
    const onOpenChange = useCallback(
        (open: boolean) => {
            if (!open) {
                return;
            }
            if (
                !selection.disabledIds?.has(asset.id) &&
                !selection
                    .getSelection()
                    .some(a => assetKey(a) === assetKey(asset))
            ) {
                // A plain click: selects the item alone, sets the Shift anchor
                selection.onItemClick(asset, []);
            }
            setTargets(selectionTargets(asset, selection.getSelection()));
        },
        [asset, selection]
    );

    return {targets, onOpenChange};
}

/**
 * Right-click menu of a list item, acting on the selection
 * (see {@link useSelectionMenuTargets}).
 */
export function AssetContextMenu({
    asset,
    onOpen,
    children,
}: PropsWithChildren<{asset: Asset; onOpen?: () => void}>) {
    const {targets, onOpenChange} = useSelectionMenuTargets(asset);

    return (
        <ContextMenu onOpenChange={onOpenChange}>
            <ContextMenuTrigger asChild>{children}</ContextMenuTrigger>
            <ContextMenuContent className="w-56">
                <AssetMenuItems
                    assets={targets}
                    variant="context"
                    onOpen={onOpen}
                />
            </ContextMenuContent>
        </ContextMenu>
    );
}

/**
 * Shared list of actions on one or several assets, rendered either in a
 * context menu or in a dropdown menu.
 */
export function AssetMenuItems({
    assets,
    variant,
    onOpen,
    context,
}: {
    assets: Asset[];
    variant: 'context' | 'dropdown';
    /** Opens the asset (single-asset menus only) */
    onOpen?: () => void;
    /** Actions that do not make sense where the menu is rendered */
    context?: ActionContext;
}) {
    const groups = useAssetActions(assets, {onOpen, context});

    return <AssetActionItems groups={groups} variant={variant} />;
}

/** Menu items of actions already built (see {@link useAssetActions}) */
export function AssetActionItems({
    groups,
    variant,
}: {
    groups: AssetAction[][];
    variant: 'context' | 'dropdown';
}) {
    const Item = variant === 'context' ? ContextMenuItem : DropdownMenuItem;
    const Sep =
        variant === 'context' ? ContextMenuSeparator : DropdownMenuSeparator;

    if (groups.length === 0) {
        return <MenuEmpty />;
    }

    return (
        <>
            {groups.map((group, gi) => (
                <Fragment key={gi}>
                    {gi > 0 ? <Sep /> : null}
                    {group.map(action => (
                        <Item
                            key={action.id}
                            disabled={action.disabled}
                            variant={
                                action.destructive ? 'destructive' : 'default'
                            }
                            onSelect={() => action.run()}
                            {...(action.href ? {asChild: true} : {})}
                        >
                            {action.href ? (
                                <a
                                    href={action.href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex w-full items-center gap-2"
                                >
                                    {action.icon} {action.label}
                                </a>
                            ) : (
                                <>
                                    {action.icon} {action.label}
                                </>
                            )}
                        </Item>
                    ))}
                </Fragment>
            ))}
        </>
    );
}

'use client';

import {Fragment, PropsWithChildren} from 'react';
import type {Asset} from '@/types/api';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/menu';
import {useAssetActions} from '@/features/assets/actions/useAssetActions';

export function AssetContextMenu({
    asset,
    onOpen,
    children,
}: PropsWithChildren<{asset: Asset; onOpen?: () => void}>) {
    return (
        <ContextMenu>
            <ContextMenuTrigger asChild>{children}</ContextMenuTrigger>
            <ContextMenuContent className="w-56">
                <AssetMenuItems
                    asset={asset}
                    variant="context"
                    onOpen={onOpen}
                />
            </ContextMenuContent>
        </ContextMenu>
    );
}

/**
 * Shared list of single-asset actions, rendered either in a context menu or in
 * a dropdown menu.
 */
export function AssetMenuItems({
    asset,
    variant,
    onOpen,
}: {
    asset: Asset;
    variant: 'context' | 'dropdown';
    onOpen?: () => void;
}) {
    const groups = useAssetActions([asset], {onOpen});
    const Item = variant === 'context' ? ContextMenuItem : DropdownMenuItem;
    const Sep =
        variant === 'context' ? ContextMenuSeparator : DropdownMenuSeparator;

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

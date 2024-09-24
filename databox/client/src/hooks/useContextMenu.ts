import React, {MouseEvent} from 'react';
import {PopoverPosition} from '@mui/material/Popover/Popover';

export type OnContextMenuOpen<T> = (
    e: MouseEvent<HTMLElement>,
    data: T,
    anchorEl?: HTMLElement
) => void;

export type ContextMenuContext<T> = {
    data: T;
    pos: PopoverPosition;
    anchorEl: HTMLElement | undefined;
};

export function useContextMenu<T>() {
    const [anchorElement, setAnchorElement] =
        React.useState<ContextMenuContext<T> | null>(null);

    const onContextMenuOpen = React.useCallback<OnContextMenuOpen<T>>(
        (e: MouseEvent<HTMLElement>, data: T, anchorEl?: HTMLElement) => {
            e.preventDefault();
            e.stopPropagation();
            setAnchorElement(p => {
                if (p && p.anchorEl === anchorEl) {
                    return null;
                }

                return {
                    data,
                    pos: {
                        left: e.clientX + 2,
                        top: e.clientY,
                    },
                    anchorEl,
                };
            });
        },
        [setAnchorElement]
    );

    return {
        contextMenu: anchorElement,
        onContextMenuOpen,
        onContextMenuClose: () => setAnchorElement(null),
    };
}

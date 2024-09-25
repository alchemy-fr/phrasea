import {PropsWithChildren} from 'react';
import {ClickAwayListener, Menu} from '@mui/material';
import {ContextMenuContext} from '../../hooks/useContextMenu.ts';

type Props<T> = PropsWithChildren<{
    id: string;
    contextMenu: ContextMenuContext<T>;
    onClose: () => void;
}>;

export default function ContextMenu<T>({
    id,
    children,
    onClose,
    contextMenu,
}: Props<T>) {
    return (
        <ClickAwayListener onClickAway={onClose} mouseEvent={'onMouseDown'}>
            <Menu
                id={`item-menu-${id}`}
                key={`item-menu-${id}`}
                keepMounted
                onClose={onClose}
                open={true}
                anchorReference={'anchorPosition'}
                anchorEl={contextMenu.anchorEl}
                anchorPosition={contextMenu.pos}
                style={{
                    pointerEvents: 'none',
                }}
                MenuListProps={{
                    style: {
                        pointerEvents: 'auto',
                    },
                }}
                BackdropProps={{
                    invisible: true,
                }}
            >
                {children}
            </Menu>
        </ClickAwayListener>
    );
}

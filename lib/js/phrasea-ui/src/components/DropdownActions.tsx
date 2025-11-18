import Menu from '@mui/material/Menu';
import React, {MouseEventHandler, ReactNode} from 'react';
import {CloseWrapper, DropdownActionsProps} from '../types';

export default function DropdownActions({
    mainButton,
    onClose,
    children,
    anchorOrigin,
    anchorPosition,
    ...menuProps
}: DropdownActionsProps) {
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);

    const open = Boolean(anchorEl);
    const handleClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        setAnchorEl(event.currentTarget?.parentElement);
    };
    const handleClose = () => {
        onClose?.();
        setAnchorEl(null);
    };

    const closeWrapper: CloseWrapper = handler => {
        return ((e: React.MouseEvent<HTMLElement>) => {
            handler?.(e);
            handleClose();
        }) as MouseEventHandler;
    };

    return (
        <>
            {mainButton({
                open,
                'onClick': handleClick,
                'aria-haspopup': 'true',
                'aria-expanded': open ? 'true' : undefined,
                'className': open ? dropdownActionsOpenClassName : undefined,
            })}
            <Menu
                disablePortal={true}
                style={{
                    width: '100%',
                }}
                anchorEl={anchorEl}
                anchorOrigin={
                    anchorOrigin ?? {
                        vertical: 'bottom',
                        horizontal: 'left',
                    }
                }
                anchorPosition={anchorPosition}
                open={open}
                onClose={handleClose}
                {...menuProps}
            >
                {children(closeWrapper).filter(c => null !== c) as ReactNode[]}
            </Menu>
        </>
    );
}

export const dropdownActionsOpenClassName = 'dropdown-actions-open';

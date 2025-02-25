import Menu, {MenuProps} from '@mui/material/Menu';
import React, {JSX, MouseEventHandler, ReactNode} from 'react';
import {ButtonBaseProps} from '@mui/material';

type CloseWrapper = (handler?: MouseEventHandler) => MouseEventHandler;

type MainButtonProps = {
    open: boolean;
    className?: string;
} & Pick<ButtonBaseProps, 'onClick' | 'aria-haspopup' | 'aria-expanded'>;

type Props = {
    mainButton: (props: MainButtonProps) => JSX.Element;
    children: (closeWrapper: CloseWrapper) => (ReactNode | null)[];
    onClose?: () => void;
} & Omit<MenuProps, 'open' | 'onClose' | 'children'>;

export type {Props as DropdownActionsProps};

export default function DropdownActions({
    mainButton,
    onClose,
    children,
    anchorOrigin,
    anchorPosition,
    ...menuProps
}: Props) {
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
        return (e => {
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

import Menu, {MenuProps} from '@mui/material/Menu';
import React, {JSX, MouseEventHandler, ReactNode} from 'react';
import {ButtonBaseProps} from "@mui/material";

type CloseWrapper = (handler: () => any) => MouseEventHandler;

type MainButtonProps = {
    open: boolean;
} & Pick<ButtonBaseProps, "onClick" | "aria-haspopup" | "aria-expanded">;

type Props = {
    mainButton: (props: MainButtonProps) => JSX.Element;
    children: (closeWrapper: CloseWrapper) => (ReactNode | null)[];
    anchorOrigin?: MenuProps['anchorOrigin'];
    anchorPosition?: MenuProps['anchorPosition'];
};

export type {Props as DropdownActionsProps};

export default function DropdownActions({mainButton, children, anchorOrigin,
    anchorPosition,}: Props) {
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);

    const open = Boolean(anchorEl);
    const handleClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        setAnchorEl(event.currentTarget?.parentElement);
    };
    const handleClose = () => {
        setAnchorEl(null);
    };

    const closeWrapper: CloseWrapper = handler => {
        return () => {
            handler && handler();
            handleClose();
        };
    };

    return (
        <>
            {mainButton({
                open,
                onClick: handleClick,
                'aria-haspopup': 'true',
                'aria-expanded': open ? 'true' : undefined,
            })}
            <Menu
                disablePortal={true}
                style={{
                    width: '100%',
                }}
                anchorEl={anchorEl}
                anchorOrigin={anchorOrigin ?? {
                    vertical: 'bottom',
                    horizontal: 'left',
                }}
                anchorPosition={anchorPosition}
                open={open}
                onClose={handleClose}
            >
                {children(closeWrapper).filter(c => null !== c) as ReactNode[]}
            </Menu>
        </>
    );
}

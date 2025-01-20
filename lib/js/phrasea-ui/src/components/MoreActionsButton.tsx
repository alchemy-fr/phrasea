import Menu from '@mui/material/Menu';
import {IconButton} from '@mui/material';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import React, {MouseEventHandler, ReactNode} from 'react';

type CloseWrapper = (handler: () => any) => MouseEventHandler;

type Props = {
    children: (closeWrapper: CloseWrapper) => (ReactNode | null)[];
};

export default function MoreActionsButton({children}: Props) {
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);

    const open = Boolean(anchorEl);
    const handleClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        setAnchorEl(event.currentTarget);
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
            <IconButton
                aria-haspopup="true"
                aria-expanded={open ? 'true' : undefined}
                onClick={handleClick}
            >
                <MoreHorizIcon />
            </IconButton>
            <Menu anchorEl={anchorEl} open={open} onClose={handleClose}>
                {children(closeWrapper).filter(c => null !== c) as ReactNode[]}
            </Menu>
        </>
    );
}

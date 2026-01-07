import {PropsWithChildren, ReactNode, useState} from 'react';
import {IconButton, Theme, useMediaQuery} from '@mui/material';
import {AppMenuProps} from './types';
import VerticalAppMenu from './VerticalAppMenu';
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import MenuIcon from '@mui/icons-material/Menu';

type Props = PropsWithChildren<{
    header?: ReactNode;
    menuChildren?: ReactNode;
}> & Omit<AppMenuProps, 'children'>;

export default function VerticalMenuLayout({
    header,
    children,
    menuChildren,
    ...appMenuProps
}: Props) {
    const menuWidth = 320;
    const isSmallView = useMediaQuery((theme: Theme) =>
        theme.breakpoints.down('md')
    );
    const [open, setOpen] = useState(!isSmallView);

    return (
        <div
            style={{
                height: '100vh',
                display: 'flex',
            }}
        >
            <div
                style={{
                    zIndex: 151,
                    flexShrink: 0,
                    position: isSmallView ? 'absolute' : undefined,
                    transition: 'transform 0.3s ease-in-out',
                    transform: !open
                        ? `translateX(-${menuWidth}px)`
                        : 'translateX(0)',
                    marginRight: open ? 0 : -menuWidth,
                    width: menuWidth,
                }}
            >
                <IconButton
                    sx={theme => ({
                        position: 'absolute',
                        top: theme.spacing(2),
                        right: 0,
                        zIndex: 151,
                        bgcolor: 'background.paper',
                        boxShadow: 1,
                        transition: 'transform 0.3s ease-in-out',
                        transform: !open
                            ? `translateX(${theme.spacing(7)})`
                            : `translateX(${theme.spacing(-2)})`,
                    })}
                    onClick={() => setOpen(o => !o)}
                >
                    {open ? <KeyboardArrowLeftIcon /> : <MenuIcon />}
                </IconButton>
                <VerticalAppMenu {...appMenuProps}>
                    {menuChildren}
                </VerticalAppMenu>
            </div>
            <div style={{
                flexGrow: 1,
            }}>

            <div
                style={{
                    marginLeft: !open || isSmallView ? 50 : 0,
                }}
            >
                {header}
            </div>
            {children}
            </div>
        </div>
    );
}

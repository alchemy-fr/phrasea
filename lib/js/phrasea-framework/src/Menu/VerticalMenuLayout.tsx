import React, {PropsWithChildren, ReactNode, useEffect, useState} from 'react';
import {IconButton, Theme, useMediaQuery, useTheme} from '@mui/material';
import {AppMenuProps, MenuClasses} from './types';
import VerticalAppMenu from './VerticalAppMenu';
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import MenuIcon from '@mui/icons-material/Menu';
import Box from '@mui/material/Box';
import {resolveSx, sumSpacing} from '@alchemy/core';

type Props = PropsWithChildren<{
    menuChildren?: ReactNode;
    defaultOpen?: boolean;
    contentSx?: React.CSSProperties | ((theme: Theme) => React.CSSProperties);
}> &
    Omit<AppMenuProps, 'children'>;

export default function VerticalMenuLayout({
    children,
    menuChildren,
    contentSx,
    defaultOpen = true,
    ...appMenuProps
}: Props) {
    const menuWidth = 320;
    const theme = useTheme();
    const isSmallScreen = useMediaQuery(theme.breakpoints.down('md'));
    const [open, setOpen] = useState(!isSmallScreen && defaultOpen);

    const buttonWidth = 40;

    return (
        <div
            style={{
                display: 'flex',
                flexDirection: 'row',
                position: 'relative',
            }}
        >
            <div
                style={{
                    zIndex: 151,
                    flexShrink: 0,
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    transition: 'transform 0.3s ease-in-out',
                    transform: !open
                        ? `translateX(max(-${menuWidth}px, -100vw))`
                        : 'translateX(0)',
                    marginRight: !isSmallScreen
                        ? open
                            ? 0
                            : -menuWidth
                        : undefined,
                    width: `min(${menuWidth}px, 100vw)`,
                }}
            >
                <IconButton
                    disableRipple={true}
                    sx={theme => ({
                        position: 'absolute',
                        top: theme.spacing(1),
                        left: `min(${menuWidth}px, 100vw)`,
                        zIndex: 151,
                        bgcolor: 'background.paper',
                        boxShadow: 1,
                        transition: 'transform 0.3s ease-in-out',
                        transform: !open
                            ? `translateX(${theme.spacing(1)})`
                            : `translateX(${sumSpacing(theme, -1, -buttonWidth)})`,
                    })}
                    onClick={() => setOpen(o => !o)}
                >
                    {open ? <KeyboardArrowLeftIcon /> : <MenuIcon />}
                </IconButton>
                <VerticalAppMenu {...appMenuProps}>
                    {menuChildren}
                </VerticalAppMenu>
            </div>
            <Box
                sx={theme => ({
                    marginLeft: !isSmallScreen && open
                        ? `min(${menuWidth}px, 100vw)`
                        : 0,
                    flexGrow: 1,
                    [`.${MenuClasses.PageHeader}`]: {
                        marginLeft:
                            !open || isSmallScreen
                                ? sumSpacing(theme, 2, buttonWidth)
                                : 0,
                    },
                    ...resolveSx(contentSx, theme),
                })}
            >
                {children}
            </Box>
        </div>
    );
}

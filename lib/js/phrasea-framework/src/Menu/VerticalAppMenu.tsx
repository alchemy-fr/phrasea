import {Box, IconButton, Theme, useMediaQuery} from '@mui/material';
import {AppLogo} from './AppLogo';
import {AppMenuProps} from './types';
import {CommonAppLeftMenu} from './CommonAppLeftMenu';
import {resolveSx} from '../../../core';
import {useState} from 'react';
import MenuIcon from '@mui/icons-material/Menu';

type Props = AppMenuProps;

export default function VerticalAppMenu({
    children,
    config,
    logoProps,
    commonMenuProps,
    sx,
}: Props) {
    const menuWidth = 320;
    const isSmallView = useMediaQuery((theme: Theme) =>
        theme.breakpoints.down('md')
    );
    const [open, setOpen] = useState(!isSmallView);

    return (
        <div
            style={{
                zIndex: 151,
                position: 'relative',
                transition: 'transform 0.3s ease-in-out',
                transform: !open
                    ? `translateX(-${menuWidth}px)`
                    : 'translateX(0)',
                marginRight: open ? 0 : -menuWidth,
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
                <MenuIcon />
            </IconButton>
            <Box
                sx={theme => ({
                    zIndex: theme.zIndex.tooltip,
                    backgroundColor: theme.palette.background.paper,
                    display: 'flex',
                    flexDirection: 'column',
                    width: menuWidth,
                    overflow: 'auto',
                    flexShrink: 0,
                    flexGrow: 0,
                    height: '100vh',
                    borderRight: `1px solid ${theme.palette.divider}`,

                    ...resolveSx(sx, theme),
                })}
            >
                <Box
                    sx={{
                        p: 2,
                    }}
                >
                    <AppLogo config={config} {...logoProps} />
                </Box>

                <Box
                    sx={{
                        flexGrow: 1,
                        overflow: 'auto',
                        position: 'relative',
                        pb: 3,
                    }}
                >
                    {children}
                </Box>

                <div>
                    <CommonAppLeftMenu config={config} {...commonMenuProps} />
                </div>
            </Box>
        </div>
    );
}

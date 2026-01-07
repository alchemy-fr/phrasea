import {Box} from '@mui/material';
import {AppLogo} from './AppLogo';
import {AppMenuProps} from './types';
import {CommonAppLeftMenu} from './CommonAppLeftMenu';
import {resolveSx} from '../../../core';

export default function VerticalAppMenu({
    children,
    config,
    logoProps,
    commonMenuProps,
    sx,
}: AppMenuProps) {
    return (
        <>
            <Box
                sx={theme => ({
                    zIndex: theme.zIndex.tooltip,
                    backgroundColor: theme.palette.background.paper,
                    display: 'flex',
                    flexDirection: 'column',
                    width: '100%',
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
        </>
    );
}

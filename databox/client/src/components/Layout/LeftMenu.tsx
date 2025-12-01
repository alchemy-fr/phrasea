import {Box} from '@mui/material';
import React from 'react';
import Logo from './Logo.tsx';
import User from './User.tsx';
import {ZIndex} from '../../themes/zIndex.ts';
import LeftPanel from '../Media/LeftPanel.tsx';

type Props = {
    leftPanelOpen: boolean;
    toggleLeftPanel?: () => void;
};

export default function LeftMenu({}: Props) {
    const menuWidth = 320;

    return (
        <>
            <Box
                sx={theme => ({
                    zIndex: ZIndex.leftPanel,
                    backgroundColor: theme.palette.background.paper,
                    display: 'flex',
                    flexDirection: 'column',
                    width: menuWidth,
                    overflow: 'auto',
                    flexShrink: 0,
                    flexGrow: 0,
                    height: '100vh',
                    borderRight: `1px solid ${theme.palette.divider}`,
                })}
            >
                <div>
                    <Logo />
                </div>

                <Box
                    sx={{
                        flexGrow: 1,
                        overflow: 'auto',
                        position: 'relative',
                        pb: 3,
                    }}
                >
                    <LeftPanel />
                </Box>

                <div>
                    <User />
                </div>
            </Box>
        </>
    );
}

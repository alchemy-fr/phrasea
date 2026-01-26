import {Box} from '@mui/material';
import {AppLogo} from './AppLogo';
import {CommonAppTopMenu} from './CommonAppTopMenu';
import React from 'react';
import {AppMenuProps} from './types';
import {resolveSx} from '../../../core';

type Props = AppMenuProps;

export default function HorizontalAppMenu({
    children,
    config,
    logoProps,
    commonMenuProps,
    sx,
}: Props) {
    return (
        <Box
            sx={theme => ({
                display: 'flex',
                alignItems: 'center',
                flexDirection: 'row',
                py: 2,
                ...resolveSx(sx, theme),
            })}
        >
            {children ?? (
                <div
                    style={{
                        flexGrow: 1,
                    }}
                >
                    {logoProps ? <AppLogo config={config} {...logoProps} /> : null}
                </div>
            )}
            <div>
                <CommonAppTopMenu config={config} {...commonMenuProps} />
            </div>
        </Box>
    );
}

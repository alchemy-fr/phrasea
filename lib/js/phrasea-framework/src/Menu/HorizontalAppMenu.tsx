import {Box} from '@mui/material';
import {AppLogo} from './AppLogo';
import {CommonAppTopMenu} from './CommonAppTopMenu';
import React from 'react';
import {AppMenuProps} from './types';
import {resolveSx} from '../../../core';

type Props = {
    sticky?: boolean;
} & AppMenuProps;

export default function HorizontalAppMenu({
    children,
    config,
    logoProps,
    commonMenuProps,
    sx,
    sticky,
}: Props) {
    return (
        <Box
            sx={theme => ({
                display: 'flex',
                alignItems: 'center',
                flexDirection: 'row',
                py: 2,
                ...(sticky
                    ? {
                          'position': 'sticky',
                          'top': 0,
                          'zIndex': theme.zIndex.appBar,
                          '&::after': {
                              content: '""',
                              position: 'absolute',
                              zIndex: -1,
                              left: 0,
                              right: 0,
                              bottom: 0,
                              top: 0,
                              backdropFilter: 'blur(10px)',
                              backgroundColor:
                                  theme.palette.mode === 'light'
                                      ? 'rgba(255, 255, 255, 0.5)'
                                      : 'rgba(0, 0, 0, 0.5)',
                          },
                      }
                    : {}),
                ...resolveSx(sx, theme),
            })}
        >
            {children ?? (
                <div
                    style={{
                        flexGrow: 1,
                    }}
                >
                    {logoProps ? (
                        <AppLogo config={config} {...logoProps} />
                    ) : null}
                </div>
            )}
            <div>
                <CommonAppTopMenu config={config} {...commonMenuProps} />
            </div>
        </Box>
    );
}

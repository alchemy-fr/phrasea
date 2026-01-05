import {Box} from '@mui/material';
import {AppLogo} from './AppLogo';
import {CommonAppTopMenu} from './CommonAppTopMenu';
import React, {PropsWithChildren} from 'react';
import {AppLogoProps, CommonMenuProps} from './types';

type Props = PropsWithChildren<{}> & AppLogoProps & CommonMenuProps;

export default function HorizontalAppBar({children,
    keycloakClient,
    appLocales,
    config,
    ...logoProps}: Props) {
    return (
        <Box
            sx={{
                display: 'flex',
                alignItems: 'center',
                flexDirection: 'row',
            }}
        >
            {children ?? (
                <div
                    style={{
                        flexGrow: 1,
                    }}
                >
                    <AppLogo {...logoProps} config={config} />
                </div>
            )}
            <div>
                <CommonAppTopMenu
                    keycloakClient={keycloakClient}
                    appLocales={appLocales}
                    config={config}
                />
            </div>
        </Box>
    );
}

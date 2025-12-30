import {Box} from '@mui/material';
import {Logo} from '../Logo.tsx';
import React, {PropsWithChildren} from 'react';
import {config, keycloakClient} from '../../init.ts';
import {appLocales} from '../../i18n.ts';
import {CommonAppTopMenu} from '@alchemy/phrasea-framework';

type Props = PropsWithChildren<{}>;

export default function AppBar({children}: Props) {
    return (
        <Box
            sx={{
                display: 'flex',
                alignItems: 'center',
                flexDirection: 'row',
            }}
        >
            {children ?? (
                <h1
                    style={{
                        flexGrow: 1,
                    }}
                >
                    <Logo />
                </h1>
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

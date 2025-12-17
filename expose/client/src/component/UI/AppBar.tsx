import {Box} from '@mui/material';
import {Logo} from '../Logo.tsx';
import React from 'react';
import {config, keycloakClient} from '../../init.ts';
import {appLocales} from '../../i18n.ts';
import {CommonAppTopMenu} from '@alchemy/phrasea-framework';

type Props = {};

export default function AppBar({}: Props) {
    return (
        <Box
            sx={{
                display: 'flex',
                alignItems: 'center',
                flexDirection: 'row',
            }}
        >
            <h1 style={{}}>
                <Logo />
            </h1>
            <div
                style={{
                    flexGrow: 1,
                }}
            ></div>
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

import {Box} from '@mui/material';
import React from 'react';
import {config, keycloakClient} from '../init.ts';
import {AppLogo, CommonAppLeftMenu} from '@alchemy/phrasea-framework';
import {defaultLocales as appLocales, rootDefaultLocale} from '@alchemy/i18n';
import Menu from './Menu.tsx';
import {routes} from '../routes.ts';
import {getPath, useNavigate} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';

type Props = {};

export default function LeftMenu({}: Props) {
    const menuWidth = 320;
    const navigate = useNavigate();
    const {t} = useTranslation();

    return (
        <>
            <Box
                sx={theme => ({
                    zIndex: 10,
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
                    <AppLogo
                        config={config}
                        appTitle={t('common.uploader', `Uploader`)}
                        onClick={() => navigate(getPath(routes.index))}
                    />
                </div>

                <Box
                    sx={{
                        flexGrow: 1,
                        overflow: 'auto',
                        position: 'relative',
                        pb: 3,
                    }}
                >
                    <Menu />
                </Box>

                <div>
                    <CommonAppLeftMenu
                        keycloakClient={keycloakClient}
                        appLocales={appLocales}
                        defaultLocale={rootDefaultLocale}
                        config={config}
                    />
                </div>
            </Box>
        </>
    );
}

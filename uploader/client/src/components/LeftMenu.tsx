import React from 'react';
import {config, keycloakClient} from '../init.ts';
import {VerticalAppMenu} from '@alchemy/phrasea-framework';
import {defaultLocales as appLocales, rootDefaultLocale} from '@alchemy/i18n';
import Menu from './Menu.tsx';
import {routes} from '../routes.ts';
import {getPath, useNavigate} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';

type Props = {};

export default function LeftMenu({}: Props) {
    const navigate = useNavigate();
    const {t} = useTranslation();

    return (
        <VerticalAppMenu
            config={config}
            logoProps={{
                onLogoClick: () => navigate(getPath(routes.index)),
                appTitle: t('common.uploader', `Uploader`),
            }}
            commonMenuProps={{
                keycloakClient,
                appLocales,
                defaultLocale: rootDefaultLocale,
            }}
        >
            <Menu />
        </VerticalAppMenu>
    );
}

import React, {PropsWithChildren} from 'react';
import {HorizontalAppMenu} from '@alchemy/phrasea-framework';
import {config, keycloakClient} from './init.ts';
import {useTranslation} from 'react-i18next';
import {appLocales} from './i18n.ts';

type Props = PropsWithChildren<{}>;

export default function DashboardBar({children}: Props) {
    const {t} = useTranslation();
    return (
        <HorizontalAppMenu
            config={config}
            logoProps={{
                appTitle: t('common.dashboard', `Dashboard`),
            }}
            commonMenuProps={{
                appLocales,
                keycloakClient,
            }}
        >
            {children}
        </HorizontalAppMenu>
    );
}

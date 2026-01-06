import React, {PropsWithChildren} from 'react';
import {HorizontalAppMenu} from '@alchemy/phrasea-framework';
import {config, keycloakClient} from './init.ts';
import {useTranslation} from 'react-i18next';

type Props = PropsWithChildren<{}>;

export default function DashboardBar({children}: Props) {
    const {t} = useTranslation();
    return (
        <HorizontalAppMenu
            config={config}
            keycloakClient={keycloakClient}
            appTitle={t('common.dashboard', `Dashboard`)}
        >
            {children}
        </HorizontalAppMenu>
    );
}

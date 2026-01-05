import React, {PropsWithChildren} from 'react';
import {HorizontalAppBar} from '@alchemy/phrasea-framework';
import {config, keycloakClient} from './init.ts';
import {useTranslation} from 'react-i18next';

type Props = PropsWithChildren<{}>;

export default function DashboardBar({children}: Props) {
    const {t} = useTranslation();
    return (
        <HorizontalAppBar
            config={config}
            keycloakClient={keycloakClient}
            appTitle={t('common.dashboard', `Dashboard`)}
        >
            {children}
        </HorizontalAppBar>
    );
}

import React, {PropsWithChildren} from 'react';
import {config, keycloakClient} from '../../init.ts';
import {appLocales} from '../../i18n.ts';
import {HorizontalAppBar} from '@alchemy/phrasea-framework';
import {useTranslation} from 'react-i18next';

type Props = PropsWithChildren<{}>;

export default function AppBar({children}: Props) {
    const {t} = useTranslation();
    return (
        <HorizontalAppBar
            keycloakClient={keycloakClient}
            appLocales={appLocales}
            config={config}
            appTitle={t('common.expose', 'Expose')}
        >
            {children}
        </HorizontalAppBar>
    );
}

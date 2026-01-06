import React, {PropsWithChildren} from 'react';
import {config, keycloakClient} from '../../init.ts';
import {appLocales} from '../../i18n.ts';
import {HorizontalAppMenu} from '@alchemy/phrasea-framework';
import {useTranslation} from 'react-i18next';

type Props = PropsWithChildren<{}>;

export default function AppBar({children}: Props) {
    const {t} = useTranslation();
    return (
        <HorizontalAppMenu
            commonMenuProps={{
                keycloakClient,
                appLocales,
            }}
            config={config}
            logoProps={{
                appTitle: t('common.expose', 'Expose'),
            }}
        >
            {children}
        </HorizontalAppMenu>
    );
}

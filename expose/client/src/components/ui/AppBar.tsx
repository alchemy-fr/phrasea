import React, {PropsWithChildren} from 'react';
import {config, keycloakClient} from '../../init.ts';
import {appLocales} from '../../i18n.ts';
import {HorizontalAppMenu} from '@alchemy/phrasea-framework';
import {useTranslation} from 'react-i18next';
import {getPath, useNavigate} from '@alchemy/navigation';
import {routes} from '../../routes.ts';

type Props = PropsWithChildren<{}>;

export default function AppBar({children}: Props) {
    const {t} = useTranslation();
    const navigate = useNavigate();

    return (
        <HorizontalAppMenu
            commonMenuProps={{
                keycloakClient,
                appLocales,
            }}
            config={config}
            logoProps={{
                appTitle: t('common.expose', 'Expose'),
                onLogoClick: () => {
                    navigate(getPath(routes.index));
                },
            }}
        >
            {children}
        </HorizontalAppMenu>
    );
}

import React, {PropsWithChildren} from 'react';
import {config, keycloakClient} from '../init.ts';
import {appLocales} from '../i18n.ts';
import {
    AppLogo,
    HorizontalAppMenu,
    MenuOrientation,
} from '@alchemy/phrasea-framework';
import {useTranslation} from 'react-i18next';
import {getPath, useNavigate} from '@alchemy/navigation';
import {routes} from '../routes.ts';
import {FlexRow} from '@alchemy/phrasea-ui';
import AppNav from './AppNav.tsx';

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
        >
            <FlexRow
                sx={{
                    gap: 1,
                    flexGrow: 1,
                }}
            >
                <AppLogo
                    config={config}
                    appTitle={t('common.expose', 'Expose')}
                    onLogoClick={() => {
                        navigate(getPath(routes.index));
                    }}
                    sx={{mr: 2}}
                />
                <AppNav orientation={MenuOrientation.Horizontal} />
            </FlexRow>
            {children}
        </HorizontalAppMenu>
    );
}

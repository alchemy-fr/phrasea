import React, {PropsWithChildren, useContext} from 'react';
import {config, keycloakClient} from '../../init.ts';
import {VerticalMenuLayout} from '@alchemy/phrasea-framework';
import ChangeThemeDialog from './ChangeThemeDialog.tsx';
import LocaleDialog from '../Locale/LocaleDialog.tsx';
import {appLocales} from '../../../translations/locales.ts';
import {rootDefaultLocale} from '@alchemy/i18n';
import {SearchContext} from '../Media/Search/SearchContext.tsx';
import {useTranslation} from 'react-i18next';
import LeftPanel from '../Media/LeftPanel.tsx';
import {useNotificationUriHandler} from '../../hooks/useNotificationUriHandler.ts';

type Props = PropsWithChildren<{
    leftPanelOpen: boolean;
    toggleLeftPanel?: () => void;
}>;

export default function AppLayout({children}: Props) {
    const searchContext = useContext(SearchContext)!;
    const notificationUriHandler = useNotificationUriHandler();
    const onLogoClick = () => searchContext.reset();
    const {t} = useTranslation();

    return (
        <VerticalMenuLayout
            config={config}
            logoProps={{
                onLogoClick,
                appTitle: t('common.databox', `Databox`),
            }}
            commonMenuProps={{
                keycloakClient,
                appLocales,
                defaultLocale: rootDefaultLocale,
                ChangeThemeDialog,
                LocaleDialogComponent: LocaleDialog,
                notificationUriHandler,
            }}
            menuChildren={<LeftPanel />}
            contentSx={{
                height: '100vh',
            }}
        >
            {children}
        </VerticalMenuLayout>
    );
}

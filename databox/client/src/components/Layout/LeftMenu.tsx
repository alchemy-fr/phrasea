import React, {useContext} from 'react';
import {config, keycloakClient} from '../../init.ts';
import {VerticalAppMenu} from '@alchemy/phrasea-framework';
import ChangeThemeDialog from './ChangeThemeDialog.tsx';
import LocaleDialog from '../Locale/LocaleDialog.tsx';
import {appLocales} from '../../../translations/locales.ts';
import {rootDefaultLocale} from '@alchemy/i18n';
import {SearchContext} from '../Media/Search/SearchContext.tsx';
import {useTranslation} from 'react-i18next';
import LeftPanel from '../Media/LeftPanel.tsx';

type Props = {
    leftPanelOpen: boolean;
    toggleLeftPanel?: () => void;
};

export default function LeftMenu({}: Props) {
    const searchContext = useContext(SearchContext)!;
    const onLogoClick = () => searchContext.reset();
    const {t} = useTranslation();

    return (
        <VerticalAppMenu
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
            }}
        >
            <LeftPanel />
        </VerticalAppMenu>
    );
}

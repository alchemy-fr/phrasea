import {Box} from '@mui/material';
import React, {useContext} from 'react';
import {ZIndex} from '../../themes/zIndex.ts';
import LeftPanel from '../Media/LeftPanel.tsx';
import {config, keycloakClient} from '../../init.ts';
import {AppLogo, CommonAppLeftMenu} from '@alchemy/phrasea-framework';
import ChangeThemeDialog from './ChangeThemeDialog.tsx';
import LocaleDialog from '../Locale/LocaleDialog.tsx';
import {appLocales} from '../../../translations/locales.ts';
import {rootDefaultLocale} from '@alchemy/i18n';
import {SearchContext} from '../Media/Search/SearchContext.tsx';
import {useTranslation} from 'react-i18next';

type Props = {
    leftPanelOpen: boolean;
    toggleLeftPanel?: () => void;
};

export default function LeftMenu({}: Props) {
    const menuWidth = 320;
    const searchContext = useContext(SearchContext)!;
    const onTitleClick = () => searchContext.reset();
    const {t} = useTranslation();

    return (
        <>
            <Box
                sx={theme => ({
                    zIndex: ZIndex.leftPanel,
                    backgroundColor: theme.palette.background.paper,
                    display: 'flex',
                    flexDirection: 'column',
                    width: menuWidth,
                    overflow: 'auto',
                    flexShrink: 0,
                    flexGrow: 0,
                    height: '100vh',
                    borderRight: `1px solid ${theme.palette.divider}`,
                })}
            >
                <div>
                    <AppLogo
                        config={config}
                        appTitle={t('common.databox', `Databox`)}
                        onClick={() => onTitleClick}
                    />
                </div>

                <Box
                    sx={{
                        flexGrow: 1,
                        overflow: 'auto',
                        position: 'relative',
                        pb: 3,
                    }}
                >
                    <LeftPanel />
                </Box>

                <div>
                    <CommonAppLeftMenu
                        keycloakClient={keycloakClient}
                        appLocales={appLocales}
                        defaultLocale={rootDefaultLocale}
                        ChangeThemeDialog={ChangeThemeDialog}
                        LocaleDialogComponent={LocaleDialog}
                        config={config}
                    />
                </div>
            </Box>
        </>
    );
}

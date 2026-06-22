import React, {PropsWithChildren, useContext} from 'react';
import {config, keycloakClient} from '../../init.ts';
import {MenuOrientation, VerticalMenuLayout} from '@alchemy/phrasea-framework';
import ChangeThemeDialog from './ChangeThemeDialog.tsx';
import LocaleDialog from '../Locale/LocaleDialog.tsx';
import {appLocales} from '../../../translations/locales.ts';
import {rootDefaultLocale} from '@alchemy/i18n';
import {SearchContext} from '../Media/Search/SearchContext.tsx';
import {useTranslation} from 'react-i18next';
import LeftPanel from '../Media/LeftPanel.tsx';
import {useNotificationUriHandler} from '../../hooks/useNotificationUriHandler.ts';
import AppNav from './AppNav.tsx';
import {Divider, ListItemIcon, ListItemText, MenuItem} from '@mui/material';
import ProfileSwitcher from '../Profile/ProfileSwitcher.tsx';
import {useAuth} from '@alchemy/react-auth';
import {UserRole} from '../../constants.ts';
import {useNavigateToModal} from '../Routing/ModalLink.tsx';
import SettingsIcon from '@mui/icons-material/Settings';
import {modalRoutes} from '../../routes.ts';

type Props = PropsWithChildren<{
    leftPanelOpen: boolean;
    toggleLeftPanel?: () => void;
}>;

export default function AppLayout({children}: Props) {
    const searchContext = useContext(SearchContext)!;
    const notificationUriHandler = useNotificationUriHandler();
    const onLogoClick = () => searchContext.reset();
    const {t} = useTranslation();
    const {user} = useAuth();
    const navigateToModal = useNavigateToModal();

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
                topChildren: <AppNav orientation={MenuOrientation.Vertical} />,
                settingsTopActions: closeWrapper => {
                    const actions = [
                        <ProfileSwitcher
                            key={'profile'}
                            closeWrapper={closeWrapper}
                        />,
                        <Divider key={'d1'} />,
                    ];

                    if (user?.roles.includes(UserRole.DataboxAdmin)) {
                        actions.push(
                            <MenuItem
                                onClick={() =>
                                    navigateToModal(
                                        modalRoutes.operationTasks.routes.create
                                    )
                                }
                                key={'operation-tasks'}
                            >
                                <ListItemIcon>
                                    <SettingsIcon />
                                </ListItemIcon>
                                <ListItemText>
                                    {t(
                                        'appbar.operationTasks',
                                        'Operation Tasks'
                                    )}
                                </ListItemText>
                            </MenuItem>
                        );
                    }

                    return actions;
                },
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

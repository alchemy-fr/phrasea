import Box from '@mui/material/Box';
import config from '../../config.ts';
import MenuItem from '@mui/material/MenuItem';
import SettingsIcon from '@mui/icons-material/Settings';
import LocaleDialog from '../Locale/LocaleDialog.tsx';
import {ListItemIcon, ListItemText} from '@mui/material';
import LocaleIcon from '../Locale/LocaleIcon.tsx';
import ChangeTheme from './ChangeTheme.tsx';
import ColorLensIcon from '@mui/icons-material/ColorLens';
import ThemeEditor from './ThemeEditor.tsx';
import React, {useContext} from 'react';
import {useTranslation} from 'react-i18next';
import {keycloakClient} from '../../api/api-client.ts';
import {useNotificationUriHandler} from '../../hooks/useNotificationUriHandler.ts';
import {appLocales, defaultLocale} from '../../../translations/locales.ts';
import i18n from '../../i18n.ts';
import {Notifications} from '@alchemy/notification';
import {DropdownActions, UserMenu} from '@alchemy/phrasea-ui';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {ThemeEditorContext} from '@alchemy/theme-editor';
import {getBestLocale} from '@alchemy/i18n/src/Locale/localeHelper.ts';
import {useModals} from '@alchemy/navigation';
import LoginIcon from '@mui/icons-material/Login';
import {DashboardMenu} from '@alchemy/phrasea-ui';

type Props = {};

export default function User({}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const themeEditorContext = useContext(ThemeEditorContext);
    const {user, logout} = useAuth();
    const {getAccountUrl, getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });
    const notificationUriHandler = useNotificationUriHandler();

    const currentLocale =
        getBestLocale(appLocales, i18n.language ? [i18n.language] : [])! ??
        defaultLocale;

    return (
        <Box
            sx={{
                display: 'flex',
                flexDirection: 'column',
                gap: 1,
                py: 2,
                borderTop: theme => `1px solid ${theme.palette.divider}`,
            }}
        >
            {user ? (
                <Notifications
                    appIdentifier={config.novuAppIdentifier!}
                    userId={user.id}
                    socketUrl={config.novuSocketUrl!}
                    apiUrl={config.novuApiUrl!}
                    uriHandler={notificationUriHandler}
                    children={({onClick, bellIcon}) => {
                        return (
                            <MenuItem onClick={onClick}>
                                <ListItemIcon>{bellIcon}</ListItemIcon>
                                <ListItemText
                                    primary={t(
                                        'notification.menu.label',
                                        'Notifications'
                                    )}
                                />
                            </MenuItem>
                        );
                    }}
                />
            ) : null}
            {!user ? (
                <MenuItem component={'a'} href={getLoginUrl()}>
                    <ListItemIcon>
                        <LoginIcon />
                    </ListItemIcon>
                    <ListItemText primary={t('menu.sign_in', 'Sign in')} />
                </MenuItem>
            ) : (
                <UserMenu
                    variant={'menu'}
                    username={user.username}
                    accountUrl={getAccountUrl()}
                    onLogout={logout}
                />
            )}

            <DropdownActions
                mainButton={({open, ...props}) => (
                    <MenuItem
                        style={{
                            color: 'inherit',
                        }}
                        selected={open}
                        {...props}
                    >
                        <ListItemIcon>
                            <SettingsIcon />
                        </ListItemIcon>
                        <ListItemText
                            primary={t('menu.settings', 'Settings')}
                        />
                    </MenuItem>
                )}
                anchorOrigin={{
                    vertical: 'top',
                    horizontal: 'right',
                }}
                keepMounted
                transformOrigin={{
                    vertical: 'top',
                    horizontal: 'left',
                }}
            >
                {closeWrapper => [
                    <MenuItem
                        key={'change_locale'}
                        onClick={closeWrapper(() => {
                            openModal(LocaleDialog);
                        })}
                    >
                        <ListItemIcon>
                            <LocaleIcon locale={currentLocale} height="25" />
                        </ListItemIcon>
                        <ListItemText
                            primary={t('locale.current', 'English')}
                        />
                    </MenuItem>,
                    <MenuItem
                        key={'change_theme'}
                        onClick={closeWrapper(() => {
                            openModal(ChangeTheme);
                        })}
                    >
                        <ListItemIcon>
                            <ColorLensIcon />
                        </ListItemIcon>
                        <ListItemText
                            primary={t('menu.change_theme', 'Change theme')}
                        />
                    </MenuItem>,
                    <MenuItem
                        key={'theme_editor'}
                        onClick={closeWrapper(() => {
                            openModal(
                                ThemeEditor,
                                {},
                                {
                                    forwardedContexts: [
                                        {
                                            context: ThemeEditorContext,
                                            value: themeEditorContext,
                                        },
                                    ],
                                }
                            );
                        })}
                    >
                        <ListItemIcon>
                            <ColorLensIcon />
                        </ListItemIcon>
                        <ListItemText
                            primary={t('menu.theme_editor', 'Theme Editor')}
                        />
                    </MenuItem>,
                ]}
            </DropdownActions>

            {config.displayServicesMenu && (
                <DashboardMenu
                    dashboardBaseUrl={config.dashboardBaseUrl}
                    children={({icon, open, ...props}) => {
                        return (
                            <MenuItem
                                selected={open}
                                style={{
                                    color: 'inherit',
                                }}
                                {...props}
                            >
                                <ListItemIcon>{icon}</ListItemIcon>
                                <ListItemText
                                    primary={t('menu.services', 'Services')}
                                />
                            </MenuItem>
                        );
                    }}
                />
            )}
        </Box>
    );
}

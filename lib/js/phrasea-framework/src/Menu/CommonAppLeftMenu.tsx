import Box from '@mui/material/Box';
import MenuItem from '@mui/material/MenuItem';
import SettingsIcon from '@mui/icons-material/Settings';
import {ListItemIcon, ListItemText} from '@mui/material';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {Notifications} from '@alchemy/notification';
import {UserMenu} from '@alchemy/phrasea-ui';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import LoginIcon from '@mui/icons-material/Login';
import {CommonMenuProps} from './types';
import SettingDropdown from './SettingDropdown';

export function CommonAppLeftMenu({
    notificationUriHandler,
    keycloakClient,
    config,
    ...settingsProps
}: CommonMenuProps) {
    const {t} = useTranslation();
    const {user, logout} = useAuth();
    const {getAccountUrl, getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });

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
            {user && config.notifications ? (
                <Notifications
                    appIdentifier={config.notifications.appIdentifier}
                    userId={user.id}
                    socketUrl={config.notifications.socketUrl}
                    apiUrl={config.notifications.apiUrl}
                    uriHandler={notificationUriHandler}
                    children={({open, onClick, bellIcon}) => {
                        return (
                            <MenuItem selected={open} onClick={onClick}>
                                <ListItemIcon>{bellIcon}</ListItemIcon>
                                <ListItemText
                                    primary={t(
                                        'framework.notification.menu.label',
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
                    <ListItemText
                        primary={t('framework.menu.sign_in', 'Sign In')}
                    />
                </MenuItem>
            ) : (
                <UserMenu
                    variant={'menu'}
                    username={user.username}
                    accountUrl={getAccountUrl()}
                    onLogout={logout}
                />
            )}

            <SettingDropdown
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
                            primary={t('framework.menu.settings', 'Settings')}
                        />
                    </MenuItem>
                )}
                config={config}
                {...settingsProps}
            />
        </Box>
    );
}

import SettingsIcon from '@mui/icons-material/Settings';
import {Box, Button, IconButton} from '@mui/material';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {Notifications} from '@alchemy/notification';
import {UserMenu} from '@alchemy/phrasea-ui';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import LoginIcon from '@mui/icons-material/Login';
import {CommonMenuProps} from './types';
import SettingDropdown from './SettingDropdown';

export function CommonAppTopMenu({
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
                alignItems: 'center',
                gap: 1,
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
                            <IconButton
                                title={t(
                                    'notification.menu.label',
                                    'Notifications'
                                )}
                                onClick={onClick}
                                color={open ? 'primary' : 'inherit'}
                            >
                                {bellIcon}
                            </IconButton>
                        );
                    }}
                    popoverProps={{
                        anchorOrigin: {
                            vertical: 'bottom',
                            horizontal: 'right',
                        },
                        transformOrigin: {
                            vertical: 'top',
                            horizontal: 'right',
                        },
                    }}
                />
            ) : null}
            {!user ? (
                <Button
                    startIcon={<LoginIcon />}
                    component={'a'}
                    href={getLoginUrl()}
                >
                    {t('menu.sign_in', 'Sign In')}
                </Button>
            ) : (
                <UserMenu
                    username={user?.username || ''}
                    accountUrl={getAccountUrl()}
                    onLogout={logout}
                />
            )}

            <SettingDropdown
                mainButton={({open, ...props}) => (
                    <IconButton
                        title={t('menu.settings', 'Settings')}
                        style={{
                            color: 'inherit',
                        }}
                        color={open ? 'primary' : 'error'}
                        {...props}
                    >
                        <SettingsIcon />
                    </IconButton>
                )}
                config={config}
                anchorOrigin={{
                    vertical: 'bottom',
                    horizontal: 'right',
                }}
                transformOrigin={{
                    vertical: 'top',
                    horizontal: 'right',
                }}
                {...settingsProps}
            />
        </Box>
    );
}

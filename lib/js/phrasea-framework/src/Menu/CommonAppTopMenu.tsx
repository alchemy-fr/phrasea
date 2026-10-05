import SettingsIcon from '@mui/icons-material/Settings';
import {Box, Button, IconButton} from '@mui/material';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {Notifications} from '@alchemy/notification';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import LoginIcon from '@mui/icons-material/Login';
import {CommonMenuProps} from './types';
import SettingDropdown from './SettingDropdown';
import UserMenu from './UserMenu';

export function CommonAppTopMenu({
    notificationUriHandler,
    apiClient,
    registerNotificationRealtime,
    keycloakClient,
    config,
    settingsTopActions,
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
            {user && config.notifications && apiClient ? (
                <Notifications
                    apiClient={apiClient}
                    userId={user.id}
                    uriHandler={notificationUriHandler}
                    registerRealtime={registerNotificationRealtime}
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
                >
                    {({open, onClick, bellIcon}) => {
                        return (
                            <IconButton
                                title={t(
                                    'framework.notification.menu.label',
                                    'Notifications'
                                )}
                                onClick={onClick}
                                color={open ? 'primary' : 'inherit'}
                            >
                                {bellIcon}
                            </IconButton>
                        );
                    }}
                </Notifications>
            ) : null}
            {!user ? (
                <Button
                    startIcon={<LoginIcon />}
                    component={'a'}
                    href={getLoginUrl()}
                >
                    {t('framework.menu.sign_in', 'Sign In')}
                </Button>
            ) : (
                <UserMenu
                    username={user.username}
                    accountUrl={getAccountUrl()}
                    onLogout={logout}
                    debugUser={config.devMode}
                    keycloakClient={keycloakClient}
                />
            )}

            <SettingDropdown
                mainButton={({open, ...props}) => (
                    <IconButton
                        title={t('framework.menu.settings', 'Settings')}
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
                topActions={settingsTopActions}
            />
        </Box>
    );
}

import React, {useContext} from 'react';
import AppBar from '@mui/material/AppBar';
import Box from '@mui/material/Box';
import Toolbar from '@mui/material/Toolbar';
import IconButton from '@mui/material/IconButton';
import Typography from '@mui/material/Typography';
import MenuIcon from '@mui/icons-material/Menu';
import Container from '@mui/material/Container';
import MenuItem from '@mui/material/MenuItem';
import {useTranslation} from 'react-i18next';
import {ListItemIcon, ListItemText} from '@mui/material';
import {SearchContext} from '../Media/Search/SearchContext';
import ColorLensIcon from '@mui/icons-material/ColorLens';
import {ZIndex} from '../../themes/zIndex';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {ThemeEditorContext} from '@alchemy/theme-editor';
import config from '../../config';
import {keycloakClient} from '../../api/api-client';
import {DashboardMenu} from '@alchemy/react-ps';
import {Notifications} from '@alchemy/notification';
import {useModals} from '@alchemy/navigation';
import ChangeTheme from './ChangeTheme';
import ThemeEditor from './ThemeEditor';
import {DropdownActions, UserMenu} from '@alchemy/phrasea-ui';
import {useNotificationUriHandler} from '../../hooks/useNotificationUriHandler.ts';
import LocaleDialog from '../Locale/LocaleDialog.tsx';
import i18n from '../../i18n.ts';
import {getBestLocale} from '@alchemy/i18n/src/Locale/localeHelper.ts';
import {appLocales, defaultLocale} from '../../../translations/locales.ts';
import LocaleIcon from '../Locale/LocaleIcon.tsx';
import SettingsIcon from '@mui/icons-material/Settings';
import {parseInlineStyle} from '../../lib/style.ts';

export const menuHeight = 42;

type Props = {
    leftPanelOpen: boolean;
    onToggleLeftPanel: () => void;
};

export default function MainAppBar({onToggleLeftPanel}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const themeEditorContext = useContext(ThemeEditorContext);
    const {user, logout} = useAuth();
    const searchContext = useContext(SearchContext)!;
    const {getAccountUrl, getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });
    const notificationUriHandler = useNotificationUriHandler();
    const onTitleClick = () => searchContext.reset();

    const currentLocale =
        getBestLocale(appLocales, i18n.language ? [i18n.language] : [])! ??
        defaultLocale;

    return (
        <div
            style={{
                position: 'relative',
                zIndex: ZIndex.mainBar,
            }}
        >
            <AppBar
                style={{
                    height: menuHeight,
                }}
                position="static"
            >
                <Container maxWidth={false}>
                    <Toolbar
                        disableGutters
                        variant={'dense'}
                        sx={{
                            height: menuHeight,
                            minHeight: 'unset',
                        }}
                    >
                        <Typography
                            variant="h1"
                            noWrap
                            component="div"
                            onClick={onTitleClick}
                            sx={{
                                fontSize: 17,
                                mr: 2,
                                display: {
                                    xs: 'none',
                                    md: 'flex',
                                },
                                cursor: 'pointer',
                            }}
                        >
                            {config.logo?.src ? (
                                <img
                                    src={config.logo.src}
                                    alt={t('common.databox', `Databox`)}
                                    style={
                                        config.logo!.style
                                            ? parseInlineStyle(
                                                  config.logo.style
                                              )
                                            : {maxHeight: 32, maxWidth: 150}
                                    }
                                />
                            ) : (
                                t('common.databox', `Databox`)
                            )}
                        </Typography>

                        <Box
                            sx={{
                                flexGrow: 1,
                                display: {xs: 'flex', md: 'none'},
                            }}
                        >
                            <IconButton
                                size="large"
                                aria-label="account of current user"
                                aria-controls="menu-appbar"
                                aria-haspopup="true"
                                onClick={onToggleLeftPanel}
                                color="inherit"
                            >
                                <MenuIcon />
                            </IconButton>
                        </Box>
                        <Typography
                            variant="h6"
                            noWrap
                            component="div"
                            onClick={onTitleClick}
                            sx={{
                                flexGrow: 1,
                                display: {
                                    xs: 'flex',
                                    md: 'none',
                                },
                                cursor: 'pointer',
                            }}
                        >
                            {t('common.databox', `Databox`)}
                        </Typography>
                        <Box
                            sx={{
                                flexGrow: 1,
                                display: {xs: 'none', md: 'flex'},
                            }}
                        ></Box>

                        {user ? (
                            <Box
                                sx={{
                                    flexGrow: 0,
                                    mr: 1,
                                }}
                            >
                                <Notifications
                                    appIdentifier={config.novuAppIdentifier!}
                                    userId={user.id}
                                    socketUrl={config.novuSocketUrl!}
                                    apiUrl={config.novuApiUrl!}
                                    uriHandler={notificationUriHandler}
                                />
                            </Box>
                        ) : null}
                        <div style={{flexGrow: 0}}>
                            {!user ? (
                                <MenuItem component={'a'} href={getLoginUrl()}>
                                    {t('menu.sign_in', 'Sign in')}
                                </MenuItem>
                            ) : (
                                <UserMenu
                                    menuHeight={menuHeight}
                                    username={user.username}
                                    accountUrl={getAccountUrl()}
                                    onLogout={logout}
                                />
                            )}
                        </div>

                        <DropdownActions
                            mainButton={props => (
                                <IconButton
                                    style={{
                                        color: 'inherit',
                                    }}
                                    {...props}
                                >
                                    <SettingsIcon />
                                </IconButton>
                            )}
                            anchorOrigin={{
                                vertical: 'top',
                                horizontal: 'right',
                            }}
                            keepMounted
                            transformOrigin={{
                                vertical: 'top',
                                horizontal: 'right',
                            }}
                            sx={{mt: `${menuHeight - 10}px`}}
                        >
                            {closeMenu => [
                                <MenuItem
                                    key={'change_locale'}
                                    onClick={() => {
                                        openModal(LocaleDialog);
                                        closeMenu();
                                    }}
                                >
                                    <ListItemIcon>
                                        <LocaleIcon
                                            locale={currentLocale}
                                            height="25"
                                        />
                                    </ListItemIcon>
                                    <ListItemText
                                        primary={t('locale.current', 'English')}
                                    />
                                </MenuItem>,
                                <MenuItem
                                    key={'change_theme'}
                                    onClick={() => {
                                        openModal(ChangeTheme);
                                        closeMenu();
                                    }}
                                >
                                    <ListItemIcon>
                                        <ColorLensIcon />
                                    </ListItemIcon>
                                    <ListItemText
                                        primary={t(
                                            'menu.change_theme',
                                            'Change theme'
                                        )}
                                    />
                                </MenuItem>,
                                <MenuItem
                                    key={'theme_editor'}
                                    onClick={() => {
                                        openModal(
                                            ThemeEditor,
                                            {},
                                            {
                                                forwardedContexts: [
                                                    {
                                                        context:
                                                            ThemeEditorContext,
                                                        value: themeEditorContext,
                                                    },
                                                ],
                                            }
                                        );
                                        closeMenu();
                                    }}
                                >
                                    <ListItemIcon>
                                        <ColorLensIcon />
                                    </ListItemIcon>
                                    <ListItemText
                                        primary={t(
                                            'menu.theme_editor',
                                            'Theme Editor'
                                        )}
                                    />
                                </MenuItem>,
                            ]}
                        </DropdownActions>

                        {config.displayServicesMenu && (
                            <div style={{flexGrow: 0}}>
                                <DashboardMenu
                                    style={{
                                        position: 'relative',
                                        marginLeft: 5,
                                    }}
                                    bodyPadding={0}
                                    size={35}
                                    dashboardBaseUrl={config.dashboardBaseUrl}
                                />
                            </div>
                        )}
                    </Toolbar>
                </Container>
            </AppBar>
        </div>
    );
}

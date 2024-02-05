import {useContext} from 'react';
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
import {zIndex} from '../../themes/zIndex';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {ThemeEditorContext} from '@alchemy/theme-editor';
import config from '../../config.ts';
import {keycloakClient} from '../../api/api-client.ts';
import {DashboardMenu} from '@alchemy/react-ps';
import {useModals} from '@alchemy/navigation';
import ChangeTheme from './ChangeTheme.tsx';
import ThemeEditor from './ThemeEditor.tsx';
import {UserMenu} from '@alchemy/phrasea-ui';

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
    const searchContext = useContext(SearchContext);
    const {getAccountUrl, getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });
    const onTitleClick = () =>
        searchContext.selectWorkspace(undefined, undefined, {
            forceReload: true,
            clearSearch: true,
        });

    return (
        <div
            style={{
                position: 'relative',
                zIndex: zIndex.mainBar,
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
                            Databox
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
                            Databox
                        </Typography>
                        <Box
                            sx={{
                                flexGrow: 1,
                                display: {xs: 'none', md: 'flex'},
                            }}
                        ></Box>

                        <Box sx={{flexGrow: 0}}>
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
                                    actions={({closeMenu}) => [
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
                                        </MenuItem>
                                    ]}
                                />
                            )}
                        </Box>

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

import * as React from 'react';
import {useContext, useState} from 'react';
import AppBar from '@mui/material/AppBar';
import Box from '@mui/material/Box';
import Toolbar from '@mui/material/Toolbar';
import IconButton from '@mui/material/IconButton';
import Typography from '@mui/material/Typography';
import Menu from '@mui/material/Menu';
import MenuIcon from '@mui/icons-material/Menu';
import Container from '@mui/material/Container';
import Avatar from '@mui/material/Avatar';
import Tooltip from '@mui/material/Tooltip';
import MenuItem from '@mui/material/MenuItem';
import {useTranslation} from 'react-i18next';
import {Divider, ListItemIcon, ListItemText} from '@mui/material';
import LogoutIcon from '@mui/icons-material/Logout';
import {SearchContext} from '../Media/Search/SearchContext';
import ColorLensIcon from '@mui/icons-material/ColorLens';
import AccountBoxIcon from '@mui/icons-material/AccountBox';
import ChangeTheme from './ChangeTheme';
import {zIndex} from '../../themes/zIndex';
import {useKeycloakUser as useUser, useKeycloakUrls} from '@alchemy/auth';
import config from "../../config.ts";
import {keycloakClient} from "../../api/api-client.ts";

export const menuHeight = 42;

type Props = {
    leftPanelOpen: boolean;
    onToggleLeftPanel: () => void;
};

export default function MainAppBar({onToggleLeftPanel}: Props) {
    const {t} = useTranslation();
    const [changeTheme, setChangeTheme] = useState(false);
    const userContext = useUser();
    const [anchorElUser, setAnchorElUser] = React.useState<null | HTMLElement>(
        null
    );
    const searchContext = useContext(SearchContext);
    const {getAccountUrl, getLoginUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });
    const onTitleClick = () =>
        searchContext.selectWorkspace(undefined, undefined, true);

    const handleOpenUserMenu = (event: React.MouseEvent<HTMLElement>) => {
        setAnchorElUser(event.currentTarget);
    };

    const handleCloseUserMenu = () => {
        setAnchorElUser(null);
    };

    const username = userContext.user?.username;

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
                {changeTheme && (
                    <ChangeTheme onClose={() => setChangeTheme(false)} />
                )}
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
                            {!username ? (
                                <MenuItem component={'a'} href={getLoginUrl()}>
                                    {t('menu.sign_in', 'Sign in')}
                                </MenuItem>
                            ) : (
                                <>
                                    <Tooltip title="Open settings">
                                        <IconButton
                                            onClick={handleOpenUserMenu}
                                            sx={{p: 0}}
                                        >
                                            <Avatar
                                                sx={{
                                                    width: menuHeight - 8,
                                                    height: menuHeight - 8,
                                                    bgcolor: 'secondary.main',
                                                    color: 'secondary.contrastText',
                                                }}
                                                alt={username}
                                                src="/broken-image.jpg"
                                            >
                                                {(
                                                    username[0] || 'U'
                                                ).toUpperCase()}
                                            </Avatar>
                                        </IconButton>
                                    </Tooltip>
                                    <Menu
                                        sx={{mt: `${menuHeight - 10}px`}}
                                        id="menu-appbar"
                                        anchorEl={anchorElUser}
                                        anchorOrigin={{
                                            vertical: 'top',
                                            horizontal: 'right',
                                        }}
                                        keepMounted
                                        transformOrigin={{
                                            vertical: 'top',
                                            horizontal: 'right',
                                        }}
                                        open={Boolean(anchorElUser)}
                                        onClose={handleCloseUserMenu}
                                    >
                                        <MenuItem
                                            component={'a'}
                                            href={getAccountUrl()}
                                        >
                                            <ListItemIcon>
                                                <AccountBoxIcon />
                                            </ListItemIcon>
                                            <ListItemText
                                                primary={t(
                                                    'menu.account',
                                                    'My account'
                                                )}
                                                secondary={username}
                                            />
                                        </MenuItem>
                                        <MenuItem
                                            onClick={() => {
                                                setChangeTheme(true);
                                                handleCloseUserMenu();
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
                                        </MenuItem>
                                        <Divider light />
                                        <MenuItem
                                            key={'logout'}
                                            onClick={() =>
                                                userContext.logout!()
                                            }
                                        >
                                            <ListItemIcon>
                                                <LogoutIcon />
                                            </ListItemIcon>
                                            <ListItemText
                                                primary={t(
                                                    'menu.logout',
                                                    'Logout'
                                                )}
                                            />
                                        </MenuItem>
                                    </Menu>
                                </>
                            )}
                        </Box>
                    </Toolbar>
                </Container>
            </AppBar>
        </div>
    );
}

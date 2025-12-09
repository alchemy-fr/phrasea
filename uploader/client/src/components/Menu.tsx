import React, {PropsWithChildren, useContext} from 'react';
import {useAuth, useKeycloakUrls} from '@alchemy/react-auth';
import {getPath, useNavigate} from '@alchemy/navigation';
import UploaderUserContext from '../context/UploaderUserContext';
import {slide as Slide, State} from 'react-burger-menu';
import {routes} from '../routes';
import {
    Divider,
    List,
    ListItemIcon,
    ListItemText,
    MenuItem,
} from '@mui/material';
import FormatAlignJustifyIcon from '@mui/icons-material/FormatAlignJustify';
import TrackChangesIcon from '@mui/icons-material/TrackChanges';
import config from '../config.ts';
import {keycloakClient} from '../oauth';
import {useTranslation} from 'react-i18next';
import LogoutIcon from '@mui/icons-material/Logout';
import HomeIcon from '@mui/icons-material/Home';
import Avatar from '@mui/material/Avatar';

type Props = PropsWithChildren<{}>;

export default function Menu({children}: Props) {
    const {user, isAuthenticated, logout} = useAuth();
    const {uploaderUser} = useContext(UploaderUserContext);
    const [open, setOpen] = React.useState(false);
    const navigate = useNavigate();
    const {t} = useTranslation();

    const close = React.useCallback(() => {
        setOpen(false);
    }, [setOpen]);
    const onStateChange = React.useCallback(
        ({isOpen}: State) => {
            setOpen(isOpen);
        },
        [setOpen]
    );

    const perms = uploaderUser?.permissions;

    const goTo = React.useCallback(
        (uri: string) => {
            return () => {
                navigate(uri);
                close();
            };
        },
        [close, navigate]
    );

    const {getAccountUrl} = useKeycloakUrls({
        keycloakClient,
        autoConnectIdP: config.autoConnectIdP,
    });
    const avatarSize = 24;

    return (
        <>
            <Slide
                pageWrapId="page-wrap"
                isOpen={open}
                onStateChange={onStateChange}
            >
                <List
                    sx={{
                        '.MuiListItemIcon-root': {
                            color: 'inherit',
                        },
                        '.MuiListItemText-secondary': {
                            color: 'secondary.contrastText',
                        },
                    }}
                >
                    <MenuItem onClick={goTo(getPath(routes.index))}>
                        <ListItemIcon>
                            <HomeIcon />
                        </ListItemIcon>
                        <ListItemText primary="Home" />
                    </MenuItem>
                    {perms?.form_schema && (
                        <MenuItem
                            onClick={goTo(
                                getPath(routes.admin.routes.formEditor)
                            )}
                        >
                            <ListItemIcon>
                                <FormatAlignJustifyIcon />
                            </ListItemIcon>
                            <ListItemText primary="Form Editor" />
                        </MenuItem>
                    )}
                    {perms?.target_data && (
                        <MenuItem
                            onClick={goTo(
                                getPath(routes.admin.routes.targetDataEditor)
                            )}
                        >
                            <ListItemIcon>
                                <TrackChangesIcon />
                            </ListItemIcon>
                            <ListItemText primary="Target Data Editor" />
                        </MenuItem>
                    )}
                    {isAuthenticated && (
                        <>
                            <Divider />
                            <MenuItem
                                component={'a'}
                                href={getAccountUrl()}
                                key={'account'}
                            >
                                <ListItemIcon>
                                    <Avatar
                                        sx={{
                                            bgcolor: 'secondary.main',
                                            color: 'secondary.contrastText',
                                            width: avatarSize,
                                            height: avatarSize,
                                        }}
                                        alt={user!.username}
                                    >
                                        {(
                                            user!.username[0] || 'U'
                                        ).toUpperCase()}
                                    </Avatar>
                                </ListItemIcon>
                                <ListItemText
                                    primary={t('menu.account', 'My account')}
                                    secondary={user!.username}
                                />
                            </MenuItem>
                            <MenuItem key={'logout'} onClick={() => logout()}>
                                <ListItemIcon>
                                    <LogoutIcon />
                                </ListItemIcon>
                                <ListItemText
                                    primary={t('menu.logout', 'Logout')}
                                />
                            </MenuItem>
                        </>
                    )}
                </List>
            </Slide>
            <div id="page-wrap">{children}</div>
        </>
    );
}

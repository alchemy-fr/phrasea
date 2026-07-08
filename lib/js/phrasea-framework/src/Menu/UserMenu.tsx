import React, {ReactNode} from 'react';
import IconButton from '@mui/material/IconButton';
import MenuItem from '@mui/material/MenuItem';
import {Divider, ListItemIcon, ListItemText} from '@mui/material';
import LogoutIcon from '@mui/icons-material/Logout';
import {useTranslation} from 'react-i18next';
import UserAvatar from '../../../phrasea-ui/src/components/UserAvatar';
import DropdownActions from '../../../phrasea-ui/src/components/DropdownActions';
import {useAuth} from '@alchemy/react-auth';
import {KeycloakClient} from '@alchemy/auth';
import AccountCircleIcon from '@mui/icons-material/AccountCircle';
import AttributionIcon from '@mui/icons-material/Attribution';

type Props = {
    variant?: 'menu' | 'icon-button';
    actions?: (props: {closeMenu: () => void}) => ReactNode[];
    accountUrl?: string;
    onLogout?: () => void;
    username: string;
    debugUser?: boolean;
    keycloakClient?: KeycloakClient;
};

export default function UserMenu({
    variant = 'icon-button',
    actions,
    accountUrl,
    onLogout,
    username,
    debugUser,
    keycloakClient,
}: Props) {
    const {t} = useTranslation();
    const {user, tokens} = useAuth();

    const isMenu = variant === 'menu';

    return (
        <DropdownActions
            mainButton={({open, ...props}) =>
                isMenu ? (
                    <MenuItem {...props} selected={open}>
                        <ListItemIcon>
                            <UserAvatar size={25} username={username} />
                        </ListItemIcon>
                        <ListItemText primary={username} />
                    </MenuItem>
                ) : (
                    <IconButton {...props}>
                        <UserAvatar
                            size={40}
                            username={username}
                            sx={{
                                m: -1,
                            }}
                        />
                    </IconButton>
                )
            }
            anchorOrigin={{
                vertical: 'bottom',
                horizontal: 'right',
            }}
            keepMounted
            transformOrigin={{
                vertical: 'top',
                horizontal: isMenu ? 'left' : 'right',
            }}
        >
            {closeMenu => {
                return [
                    accountUrl ? (
                        <MenuItem
                            component={'a'}
                            key={'account'}
                            href={accountUrl}
                        >
                            <ListItemIcon>
                                <AccountCircleIcon />
                            </ListItemIcon>
                            <ListItemText
                                primary={t('lib.ui.menu.account', 'My account')}
                                secondary={username}
                            />
                        </MenuItem>
                    ) : null,

                    ...(debugUser
                        ? [
                              <MenuItem
                                  key={'debug-jwt'}
                                  onClick={() => {
                                      closeMenu();
                                      // eslint-disable-next-line no-console
                                      console.info(
                                          'Current authenticated User',
                                          user
                                      );

                                      // eslint-disable-next-line no-console
                                      console.info('Token', tokens);

                                      // eslint-disable-next-line no-console
                                      console.info(
                                          'Access Token',
                                          keycloakClient?.client.getDecodedToken()
                                      );
                                      // eslint-disable-next-line no-console
                                      console.info(
                                          'ID Token',
                                          keycloakClient?.client.getDecodedIdToken()
                                      );
                                      alert(
                                          `User info (also dumped to console):\n\n${JSON.stringify(user, null, 2)}`
                                      );
                                  }}
                              >
                                  <ListItemIcon>
                                      <AttributionIcon />
                                  </ListItemIcon>
                                  <ListItemText
                                      primary={t(
                                          'lib.ui.menu.debug_user',
                                          'Debug User'
                                      )}
                                  />
                              </MenuItem>,
                          ]
                        : []),

                    ...(actions ? actions({closeMenu}) : []),

                    ...(onLogout
                        ? [
                              <Divider key={'div-um'} />,
                              <MenuItem onClick={onLogout} key={'logout'}>
                                  <ListItemIcon>
                                      <LogoutIcon />
                                  </ListItemIcon>
                                  <ListItemText
                                      primary={t(
                                          'lib.ui.menu.logout',
                                          'Logout'
                                      )}
                                  />
                              </MenuItem>,
                          ]
                        : []),
                ];
            }}
        </DropdownActions>
    );
}

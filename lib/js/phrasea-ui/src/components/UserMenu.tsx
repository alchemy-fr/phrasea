import React, {ReactNode} from 'react';
import IconButton from '@mui/material/IconButton';
import MenuItem from '@mui/material/MenuItem';
import {Divider, ListItemIcon, ListItemText} from '@mui/material';
import LogoutIcon from '@mui/icons-material/Logout';
import AccountBoxIcon from '@mui/icons-material/AccountBox';
import {useTranslation} from 'react-i18next';
import UserAvatar from './UserAvatar';
import DropdownActions from './DropdownActions';

type Props = {
    variant?: 'menu' | 'icon-button';
    actions?: (props: {closeMenu: () => void}) => ReactNode[];
    accountUrl?: string;
    onLogout?: () => void;
    username: string;
};

export default function UserMenu({
    variant = 'icon-button',
    actions,
    accountUrl,
    onLogout,
    username,
}: Props) {
    const {t} = useTranslation();

    const isMenu = variant === 'menu';

    return (
        <DropdownActions
            mainButton={props => (
                isMenu ? (
                    <MenuItem {...props}>
                        <ListItemIcon>
                            <UserAvatar size={25} username={username} />
                        </ListItemIcon>
                        <ListItemText primary={username} />
                    </MenuItem>
                ) : (
                <IconButton {...props}>
                    <UserAvatar size={40} username={username} />
                </IconButton>
            ))}
            anchorOrigin={{
                vertical: 'top',
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
                                <AccountBoxIcon />
                            </ListItemIcon>
                            <ListItemText
                                primary={t('lib.ui.menu.account', 'My account')}
                                secondary={username}
                            />
                        </MenuItem>
                    ) : null,

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

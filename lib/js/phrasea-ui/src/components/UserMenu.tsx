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
    actions?: (props: {closeMenu: () => void}) => ReactNode[];
    accountUrl?: string;
    onLogout?: () => void;
    menuHeight: number;
    username: string;
};

export default function UserMenu({
    actions,
    accountUrl,
    onLogout,
    menuHeight,
    username,
}: Props) {
    const {t} = useTranslation();

    return (
        <DropdownActions
            mainButton={props => (
                <IconButton {...props}>
                    <UserAvatar size={menuHeight - 8} username={username} />
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

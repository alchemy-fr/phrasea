import React, {ReactNode} from 'react';
import IconButton from '@mui/material/IconButton';
import Menu from '@mui/material/Menu';
import Tooltip from '@mui/material/Tooltip';
import MenuItem from '@mui/material/MenuItem';
import {Divider, ListItemIcon, ListItemText} from '@mui/material';
import LogoutIcon from '@mui/icons-material/Logout';
import AccountBoxIcon from '@mui/icons-material/AccountBox';
import {useTranslation} from 'react-i18next';
import UserAvatar from './UserAvatar';

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
    const [anchorElUser, setAnchorElUser] = React.useState<null | HTMLElement>(
        null
    );

    const handleOpenUserMenu = (event: React.MouseEvent<HTMLElement>) => {
        setAnchorElUser(event.currentTarget);
    };

    const handleCloseUserMenu = () => {
        setAnchorElUser(null);
    };

    let menuItems: ReactNode[] = [];
    if (accountUrl) {
        menuItems.push(
            <MenuItem component={'a'} href={accountUrl} key={'account'}>
                <ListItemIcon>
                    <AccountBoxIcon />
                </ListItemIcon>
                <ListItemText
                    primary={t('lib.ui.menu.account', 'My account')}
                    secondary={username}
                />
            </MenuItem>
        );
    }
    if (actions) {
        menuItems = menuItems.concat(
            actions({
                closeMenu: handleCloseUserMenu,
            })
        );
    }
    if (onLogout) {
        menuItems.push(<Divider key={'logout_div'} light />);
        menuItems.push(
            <MenuItem key={'logout'} onClick={onLogout}>
                <ListItemIcon>
                    <LogoutIcon />
                </ListItemIcon>
                <ListItemText primary={t('lib.ui.menu.logout', 'Logout')} />
            </MenuItem>
        );
    }

    return (
        <>
            <Tooltip title="Open settings">
                <IconButton onClick={handleOpenUserMenu} sx={{p: 0}}>
                    <UserAvatar size={menuHeight - 8} username={username} />
                </IconButton>
            </Tooltip>
            <Menu
                sx={{mt: `${menuHeight - 10}px`}}
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
                {menuItems}
            </Menu>
        </>
    );
}

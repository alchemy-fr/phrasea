import {MenuItemProps} from '@mui/material';
import {Link} from 'react-router-dom';
import {getPath} from '@alchemy/navigation';
import {resolveSx} from '@alchemy/core';
import {NavButtonProps} from './types';
import MenuItem from '@mui/material/MenuItem';

type Props = NavButtonProps & MenuItemProps;

export default function NavMenuItem({
    route,
    routeParams,
    location,
    sx,
    ...buttonProps
}: Props) {
    return (
        <MenuItem
            component={Link}
            to={route ? getPath(route, routeParams) : undefined}
            sx={
                location &&
                route &&
                location.pathname === getPath(route, routeParams)
                    ? theme => ({
                          backgroundColor: theme.palette.action.selected,
                          ...resolveSx(sx, theme),
                      })
                    : sx
            }
            {...buttonProps}
        />
    );
}

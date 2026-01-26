import {Button, ButtonProps} from '@mui/material';
import {Link} from 'react-router-dom';
import {getPath} from '@alchemy/navigation';
import {resolveSx} from '@alchemy/core';
import {NavButtonProps} from './types';

type Props = NavButtonProps & ButtonProps;

export default function NavButton({
    route,
    routeParams,
    location,
    sx,
    ...buttonProps
}: Props) {
    return (
        <Button
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

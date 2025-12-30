import React, {useContext} from 'react';
import {getPath, useNavigate} from '@alchemy/navigation';
import UploaderUserContext from '../context/UploaderUserContext';
import {routes} from '../routes';
import {List, ListItemIcon, ListItemText, MenuItem} from '@mui/material';
import FormatAlignJustifyIcon from '@mui/icons-material/FormatAlignJustify';
import TrackChangesIcon from '@mui/icons-material/TrackChanges';

type Props = {};

export default function Menu({}: Props) {
    const {uploaderUser} = useContext(UploaderUserContext);
    const navigate = useNavigate();

    const perms = uploaderUser?.permissions;

    const goTo = React.useCallback(
        (uri: string) => {
            return () => {
                navigate(uri);
            };
        },
        [navigate]
    );

    return (
        <>
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
                {perms?.form_schema && (
                    <MenuItem
                        onClick={goTo(getPath(routes.admin.routes.formEditor))}
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
            </List>
        </>
    );
}

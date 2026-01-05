import React, {useContext} from 'react';
import {getPath, useLocation, useNavigate} from '@alchemy/navigation';
import UploaderUserContext from '../context/UploaderUserContext';
import {routes} from '../routes';
import {List, ListItemIcon, ListItemText, MenuItem} from '@mui/material';
import FormatAlignJustifyIcon from '@mui/icons-material/FormatAlignJustify';
import TrackChangesIcon from '@mui/icons-material/TrackChanges';
import {useTranslation} from 'react-i18next';

type Props = {};

export default function Menu({}: Props) {
    const {uploaderUser} = useContext(UploaderUserContext);
    const navigate = useNavigate();
    const location = useLocation();
    const {t} = useTranslation();

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
                        onClick={goTo(getPath(routes.admin.routes.formSchema))}
                        selected={location.pathname.startsWith(
                            getPath(routes.admin.routes.formSchema)
                        )}
                    >
                        <ListItemIcon>
                            <FormatAlignJustifyIcon />
                        </ListItemIcon>
                        <ListItemText
                            primary={t('menu.form_editor', `Form Editor`)}
                        />
                    </MenuItem>
                )}
                {perms?.target_data && (
                    <MenuItem
                        onClick={goTo(
                            getPath(routes.admin.routes.targetDataEditor)
                        )}
                        selected={location.pathname.startsWith(
                            getPath(routes.admin.routes.targetDataEditor)
                        )}
                    >
                        <ListItemIcon>
                            <TrackChangesIcon />
                        </ListItemIcon>
                        <ListItemText
                            primary={t(
                                'menu.target_data_editor',
                                `Target Data Editor`
                            )}
                        />
                    </MenuItem>
                )}
            </List>
        </>
    );
}

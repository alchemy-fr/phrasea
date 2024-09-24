import React from 'react';
import {useCollectionStore} from '../../store/collectionStore';
import WorkspaceMenuItem, {
    cActionClassName,
    workspaceItemClassName,
} from './WorkspaceMenuItem';
import {getWorkspaces} from '../../api/collection';
import {Workspace} from '../../types';
import {alpha, Box} from '@mui/material';
import {collectionItemClassName} from './CollectionMenuItem';

type Props = {};

function CollectionsPanel({}: Props) {
    const [workspaces, setWorkspaces] = React.useState<Workspace[]>([]);

    const setRootCollections = useCollectionStore(
        state => state.setRootCollections
    );

    React.useEffect(() => {
        getWorkspaces().then(result => {
            setRootCollections(result);
            setWorkspaces(result);
        });
    }, []);

    return (
        <Box
            sx={theme => ({
                [`.${workspaceItemClassName}`]: {
                    'backgroundColor': theme.palette.primary.main,
                    'color': theme.palette.primary.contrastText,
                    [`.${cActionClassName}`]: {
                        visibility: 'hidden',
                    },
                    [`&:hover .${cActionClassName}`]: {
                        visibility: 'visible',
                    },
                    '.MuiListItemSecondaryAction-root': {
                        zIndex: 1,
                    },
                    [`.MuiListItemButton-root.Mui-selected`]: {
                        backgroundColor: theme.palette.secondary.main,
                    },
                },
                '.MuiListItemIcon-root': {
                    color: 'inherit',
                },
                [`.${collectionItemClassName}`]: {
                    [`.${cActionClassName}`]: {
                        height: '100%',
                        visibility: 'hidden',
                    },
                    [`&:hover .${cActionClassName}`]: {
                        visibility: 'visible',
                    },
                    [`&:hover .MuiListItemSecondaryAction-root`]: {
                        bgcolor: alpha(theme.palette.common.white, 0.85),
                        borderRadius: 50,
                    },
                    '.MuiListItemIcon-root': {
                        minWidth: 35,
                    },
                },
            })}
        >
            {workspaces.map(w => (
                <WorkspaceMenuItem data={w} key={w.id} />
            ))}
        </Box>
    );
}

export default React.memo(CollectionsPanel);

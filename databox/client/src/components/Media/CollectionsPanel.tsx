import React from 'react';
import WorkspaceMenuItem, {
    cActionClassName,
    workspaceItemClassName,
} from './WorkspaceMenuItem';
import {alpha, Box, CircularProgress} from '@mui/material';
import {collectionItemClassName} from './CollectionMenuItem';
import {useWorkspaceStore} from '../../store/workspaceStore.ts';
import useEffectOnce from '@alchemy/react-hooks/src/useEffectOnce';
import {dropdownActionsOpenClassName, FlexRow} from '@alchemy/phrasea-ui';

type Props = {};

function CollectionsPanel({}: Props) {
    const loadWorkspaces = useWorkspaceStore(state => state.load);
    const loading = useWorkspaceStore(state => state.loading);
    const workspaces = useWorkspaceStore(state => state.workspaces);

    useEffectOnce(() => {
        loadWorkspaces();
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
                    [`&:hover, &:has(.${dropdownActionsOpenClassName})`]: {
                        [`.${cActionClassName}`]: {
                            visibility: 'visible',
                        },
                    },
                    '.MuiListItemSecondaryAction-root': {
                        zIndex: 1,
                    },
                    [`.MuiListItemButton-root.Mui-selected`]: {
                        backgroundColor: theme.palette.secondary.main,
                        color: theme.palette.secondary.contrastText,
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
                    [`&:hover, &:has(.${dropdownActionsOpenClassName})`]: {
                        [`.${cActionClassName}`]: {
                            visibility: 'visible',
                        },
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
            {loading ? (
                <FlexRow
                    style={{
                        marginTop: '20vh',
                        justifyContent: 'center',
                    }}
                >
                    <CircularProgress style={{display: 'block'}} />
                </FlexRow>
            ) : (
                <>
                    {workspaces?.map(w => (
                        <WorkspaceMenuItem data={w} key={w.id} />
                    ))}
                </>
            )}
        </Box>
    );
}

export default React.memo(CollectionsPanel);

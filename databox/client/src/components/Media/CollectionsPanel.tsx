import React from 'react';
import WorkspaceMenuItem, {
    cActionClassName,
    workspaceItemClassName,
} from './WorkspaceMenuItem';
import {alpha, Box, CircularProgress} from '@mui/material';
import CollectionMenuItem, {
    collectionItemClassName,
} from './CollectionMenuItem';
import {useWorkspaceStore} from '../../store/workspaceStore.ts';
import {dropdownActionsOpenClassName, FlexRow} from '@alchemy/phrasea-ui';
import SearchBar from '../Ui/SearchBar.tsx';
import {useSearch} from '../../hooks/useSearch.ts';
import {getCollections} from '../../api/collection.ts';
import {Collection, Workspace} from '../../types.ts';
import {useCollectionStore} from '../../store/collectionStore.ts';

type Props = {};

function CollectionsPanel({}: Props) {
    const loadWorkspaces = useWorkspaceStore(state => state.load);
    const loading = useWorkspaceStore(state => state.loading);
    const workspaces = useWorkspaceStore(state => state.workspaces);
    const updateCollection = useCollectionStore(
        state => state.updateCollection
    );
    const getFreshCollections = useCollectionStore(
        state => state.getFreshCollections
    );

    const {
        searchQuery,
        setSearchQuery,
        results,
        searchResult,
        isSearch,
        searchHandler,
    } = useSearch<Collection, Workspace>({
        items: workspaces,
        loadItems: loadWorkspaces,
        hasMore: false,
        search: (query, nextUrl) => {
            const collections = getCollections({nextUrl, query});

            collections.then(res => {
                res.result.forEach(c => updateCollection(c));
            });

            return collections;
        },
    });

    return (
        <>
            <SearchBar
                name="collections-search"
                searchQuery={searchQuery}
                setSearchQuery={setSearchQuery}
                loading={searchResult.loading}
                searchHandler={searchHandler}
            />
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
                        {isSearch
                            ? getFreshCollections(results as Collection[])?.map(
                                  c => (
                                      <CollectionMenuItem
                                          isSearch={true}
                                          collection={c}
                                          key={c.id}
                                          absolutePath={c.id}
                                          level={0}
                                          workspace={c.workspace}
                                      />
                                  )
                              )
                            : workspaces?.map(w => (
                                  <WorkspaceMenuItem data={w} key={w.id} />
                              ))}
                    </>
                )}
            </Box>
        </>
    );
}

export default React.memo(CollectionsPanel);

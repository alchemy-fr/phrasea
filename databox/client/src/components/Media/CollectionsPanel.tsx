import React, {useContext} from 'react';
import WorkspaceMenuItem, {
    cActionClassName,
    workspaceItemClassName,
} from './WorkspaceMenuItem';
import {
    alpha,
    Box,
    CircularProgress,
    ListItemIcon,
    ListItemText,
    MenuItem,
} from '@mui/material';
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
import DeleteIcon from '@mui/icons-material/Delete';
import {useTranslation} from 'react-i18next';
import {SearchContext} from './Search/SearchContext.tsx';
import {BuiltInField} from './Search/search.ts';
import SavedSearchList from './Search/SavedSearch/SavedSearchList.tsx';

type Props = {};

function CollectionsPanel({}: Props) {
    const searchContext = useContext(SearchContext)!;
    const loadWorkspaces = useWorkspaceStore(state => state.load);
    const loading = useWorkspaceStore(state => state.loading);
    const workspaces = useWorkspaceStore(state => state.workspaces);
    const updateCollection = useCollectionStore(
        state => state.updateCollection
    );
    const getFreshCollections = useCollectionStore(
        state => state.getFreshCollections
    );
    const {t} = useTranslation();

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
            <SavedSearchList />
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
                        <Box
                            sx={theme => ({
                                mt: 3,
                                pt: 1,
                                color: 'grey',
                                borderTop: `1px solid ${theme.palette.divider}`,
                            })}
                        >
                            <MenuItem
                                selected={
                                    searchContext.workspaces.length === 0 &&
                                    searchContext.collections.length === 0 &&
                                    searchContext.conditions.length === 1 &&
                                    searchContext.conditions[0].id ===
                                        BuiltInField.Deleted
                                }
                                onClick={() => {
                                    searchContext.resetWithCondition({
                                        id: BuiltInField.Deleted,
                                        query: `${BuiltInField.Deleted} = true`,
                                    });
                                }}
                            >
                                <ListItemIcon>
                                    <DeleteIcon />
                                </ListItemIcon>
                                <ListItemText
                                    primary={t(
                                        'collection_panel.trash',
                                        'Trash'
                                    )}
                                />
                            </MenuItem>
                        </Box>
                    </>
                )}
            </Box>
        </>
    );
}

export default React.memo(CollectionsPanel);

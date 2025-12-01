import React, {useContext} from 'react';
import WorkspaceMenuItem, {
    cActionClassName,
    workspaceItemClassName,
} from './WorkspaceMenuItem';
import {
    Box,
    CircularProgress,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    ListSubheader,
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
                    '.MuiListItem-root': {
                        borderRadius: 3,
                        mx: 1,
                        overflow: 'hidden',
                        width: 'auto',
                        minWidth: 35,
                    },
                    '.MuiListItemSecondaryAction-root': {
                        zIndex: 1,
                        right: theme.spacing(1),
                    },
                    '.MuiListItemIcon-root': {
                        color: 'inherit',
                    },
                    [`.${workspaceItemClassName}`]: {
                        '.MuiListItemButton-root': {
                            pl: 1,
                        },
                        [`.${cActionClassName}`]: {
                            visibility: 'hidden',
                        },
                        [`&:hover, &:has(.${dropdownActionsOpenClassName})`]: {
                            [`.${cActionClassName}`]: {
                                visibility: 'visible',
                            },
                        },
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
                    },
                })}
            >
                <ListSubheader>
                    {t('workspaces.list.title', 'Workspaces')}
                </ListSubheader>
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
                                borderTop: `1px solid ${theme.palette.divider}`,
                            })}
                        >
                            <ListItem disablePadding>
                                <ListItemButton
                                    selected={
                                        searchContext.workspaces.length === 0 &&
                                        searchContext.collections.length ===
                                            0 &&
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
                                </ListItemButton>
                            </ListItem>
                        </Box>
                    </>
                )}
            </Box>
        </>
    );
}

export default React.memo(CollectionsPanel);

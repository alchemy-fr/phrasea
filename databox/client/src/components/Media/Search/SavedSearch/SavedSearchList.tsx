import {useSearch} from '../../../../hooks/useSearch.ts';
import {SavedSearch} from '../../../../types.ts';
import {useSavedSearchStore} from '../../../../store/savedSearchStore.ts';
import {
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    ListSubheader,
    MenuItem,
} from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import React from 'react';
import {SearchContext} from '../SearchContext.tsx';
import {cActionClassName} from '../../WorkspaceMenuItem.tsx';
import ModalLink from '../../../Routing/ModalLink.tsx';
import {modalRoutes} from '../../../../routes.ts';
import EditIcon from '@mui/icons-material/Edit';
import {replaceHighlight} from '../../Asset/Attribute/AttributeHighlights.tsx';
import {MoreActionsButton} from '@alchemy/phrasea-ui';
import {getSavedSearches} from '../../../../api/savedSearch.ts';
import PageviewIcon from '@mui/icons-material/Pageview';
import {useTranslation} from 'react-i18next';

type Props = {};

export default function SavedSearchList({}: Props) {
    const searchContext = React.useContext(SearchContext);
    const loadSavedSearches = useSavedSearchStore(state => state.load);
    const deleteSavedSearch = useSavedSearchStore(state => state.deleteItem);
    const searches = useSavedSearchStore(state => state.searches);
    const {t} = useTranslation();

    const {results} = useSearch<SavedSearch>({
        items: searches,
        loadItems: loadSavedSearches,
        hasMore: false,
        search: async (query, nextUrl) => {
            return await getSavedSearches(nextUrl, {query});
        },
    });

    const onDelete = (id: string) => {
        deleteSavedSearch(id);
    };

    if (!results || results.length === 0) {
        return null;
    }

    return (
        <>
            <ListSubheader>
                {t('saved_search.list.title', 'Saved Searches')}
            </ListSubheader>

            {results.map(search => (
                <ListItem
                    key={search.id}
                    secondaryAction={
                        <span className={cActionClassName}>
                            <MoreActionsButton
                                disablePortal={false}
                                anchorOrigin={{
                                    vertical: 'bottom',
                                    horizontal: 'left',
                                }}
                            >
                                {closeWrapper => [
                                    search.capabilities.canEdit ? (
                                        <MenuItem
                                            key="edit"
                                            onClick={closeWrapper()}
                                            component={ModalLink}
                                            route={
                                                modalRoutes.savedSearch.routes
                                                    .manage
                                            }
                                            params={{
                                                id: search.id,
                                                tab: 'edit',
                                            }}
                                            aria-label="edit"
                                        >
                                            <ListItemIcon>
                                                <EditIcon />
                                            </ListItemIcon>
                                            <ListItemText
                                                primary={t(
                                                    'saved_search.item.edit',
                                                    'Edit'
                                                )}
                                            />
                                        </MenuItem>
                                    ) : null,
                                    search.capabilities.canDelete ? (
                                        <MenuItem
                                            key="delete"
                                            onClick={closeWrapper(() =>
                                                onDelete(search.id)
                                            )}
                                            aria-label="delete"
                                        >
                                            <ListItemIcon>
                                                <DeleteIcon color={'error'} />
                                            </ListItemIcon>
                                            <ListItemText
                                                primary={t(
                                                    'saved_search.item.delete',
                                                    'Delete'
                                                )}
                                            />
                                        </MenuItem>
                                    ) : null,
                                ]}
                            </MoreActionsButton>
                        </span>
                    }
                    disablePadding
                >
                    <ListItemButton
                        selected={searchContext!.searchId === search.id}
                        onClick={() => {
                            console.log('search', search);
                            searchContext!.loadSearch(search);
                        }}
                        role={undefined}
                    >
                        <ListItemIcon>
                            <PageviewIcon />
                        </ListItemIcon>
                        <ListItemText
                            primary={
                                search.title
                                    ? replaceHighlight(search.title)
                                    : search.title
                            }
                        />
                    </ListItemButton>
                </ListItem>
            ))}
        </>
    );
}

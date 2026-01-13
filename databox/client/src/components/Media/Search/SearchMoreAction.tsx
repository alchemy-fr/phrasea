import React from 'react';
import {getSearchData, putSavedSearch} from '../../../api/savedSearch.ts';
import {toast} from 'react-toastify';
import {TSearchContext} from './SearchContext.tsx';
import {useSavedSearchStore} from '../../../store/savedSearchStore.ts';
import {useTranslation} from 'react-i18next';
import SaveIcon from '@mui/icons-material/Save';
import SaveSearchDialog from './SavedSearch/SaveSearchDialog.tsx';
import {useModals} from '@alchemy/navigation';
import {ListItemIcon, MenuItem} from '@mui/material';
import {MoreActionsButton} from '@alchemy/phrasea-ui';
import SearchOffIcon from '@mui/icons-material/SearchOff';

type Props = {
    search: TSearchContext;
};

export default function SearchMoreAction({search}: Props) {
    const {openModal} = useModals();
    const {t} = useTranslation();
    const [updatingSearch, setUpdatingSearch] = React.useState(false);
    const [lastSavedChecksum, setLastSavedChecksum] = React.useState<
        string | undefined
    >(search.searchId && search.hasSearch ? search.searchChecksum : undefined);
    const updateSavedSearch = useSavedSearchStore(state => state.updateItem);

    React.useEffect(() => {
        if (search.searchId && search.hasSearch) {
            setLastSavedChecksum(search.searchChecksum);
        }
    }, [search.searchId]);

    const updateSearch = async () => {
        setUpdatingSearch(true);
        try {
            updateSavedSearch(
                await putSavedSearch(search.searchId!, {
                    data: getSearchData(search),
                })
            );
            setLastSavedChecksum(search.searchChecksum);
            toast.success(
                t(
                    'search.update_success',
                    'Search was updated successfully!'
                ) as string
            );
        } finally {
            setUpdatingSearch(false);
        }
    };

    return (
        <>
            <MoreActionsButton
                anchorOrigin={{
                    vertical: 'bottom',
                    horizontal: 'left',
                }}
            >
                {closeWrapper => [
                    <MenuItem
                        disabled={!search.hasSearch}
                        key={'clear_search'}
                        onClick={closeWrapper(() => search.reset())}
                    >
                        <ListItemIcon>
                            <SearchOffIcon />
                        </ListItemIcon>
                        {t('search.more_options.clear_search', 'Clear search')}
                    </MenuItem>,
                    <MenuItem
                        key={'save_search'}
                        // loading={updatingSearch}
                        disabled={
                            updatingSearch ||
                            !search.hasSearch ||
                            (!!lastSavedChecksum &&
                                lastSavedChecksum === search.searchChecksum)
                        }
                        onClick={closeWrapper(() => {
                            search.searchId
                                ? updateSearch()
                                : openModal(SaveSearchDialog, {
                                      search,
                                      onCreate: savedSearch => {
                                          search.setSearchId(savedSearch.id);
                                          setLastSavedChecksum(
                                              search.searchChecksum
                                          );
                                      },
                                  });
                        })}
                    >
                        <ListItemIcon>
                            <SaveIcon />
                        </ListItemIcon>
                        {search.searchId
                            ? t('search.update_search', 'Update Search')
                            : t('search.save_search', 'Save Search')}
                    </MenuItem>,
                ]}
            </MoreActionsButton>
        </>
    );
}

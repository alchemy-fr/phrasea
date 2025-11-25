import {generateQueryId} from './query.ts';
import {Box, Button} from '@mui/material';
import {useTranslation} from 'react-i18next';
import React from 'react';
import SearchCondition from './SearchCondition.tsx';
import {useModals} from '@alchemy/navigation';
import SearchConditionDialog from './SearchConditionDialog.tsx';
import AddIcon from '@mui/icons-material/Add';
import {useResolveASTs} from './useResolveASTs.ts';
import {
    useAttributeDefinitionStore,
    useIndexBySearchSlug,
    useIndexBySlug,
} from '../../../../store/attributeDefinitionStore.ts';
import SaveIcon from '@mui/icons-material/Save';
import SaveSearchDialog from '../SavedSearch/SaveSearchDialog.tsx';
import {TSearchContext} from '../SearchContext.tsx';
import {getSearchData, putSavedSearch} from '../../../../api/savedSearch.ts';
import {LoadingButton} from '@mui/lab';
import {toast} from 'react-toastify';

type Props = {
    search: TSearchContext;
};

export default function SearchConditions({search}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const {load, loaded} = useAttributeDefinitionStore();
    const definitionsIndexBySlug = useIndexBySlug();
    const definitionsIndexBySearchSlug = useIndexBySearchSlug();
    const [updatingSearch, setUpdatingSearch] = React.useState(false);
    const [lastSavedChecksum, setLastSavedChecksum] = React.useState<
        string | undefined
    >(search.searchId ? search.searchChecksum : undefined);

    React.useEffect(() => {
        if (!loaded) {
            load(t);
        }
    }, [loaded, t, load]);

    const asts = useResolveASTs({
        conditions: search.conditions,
        loaded,
        definitionsIndexBySlug,
        definitionsIndexBySearchSlug,
    });

    const updateSearch = async () => {
        setUpdatingSearch(true);
        try {
            await putSavedSearch(search.searchId!, {
                data: getSearchData(search),
            });
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
        <Box
            sx={{
                mr: -1,
            }}
        >
            {asts.map(resolvedAst => {
                return (
                    <SearchCondition
                        key={resolvedAst.condition.id}
                        condition={resolvedAst.condition}
                        query={resolvedAst.query}
                        onDelete={search.removeCondition}
                        onUpsert={search.upsertCondition}
                    />
                );
            })}
            <Button
                startIcon={<AddIcon />}
                onClick={() => {
                    openModal(SearchConditionDialog, {
                        onUpsert: search.upsertCondition,
                        condition: {
                            id: generateQueryId(),
                            query: '',
                        },
                    });
                }}
            >
                {t('search_condition.add_condition', 'Add Condition')}
            </Button>
            <LoadingButton
                loading={updatingSearch}
                disabled={
                    updatingSearch ||
                    (!!lastSavedChecksum &&
                        lastSavedChecksum === search.searchChecksum)
                }
                startIcon={<SaveIcon />}
                onClick={() => {
                    search.searchId
                        ? updateSearch()
                        : openModal(SaveSearchDialog, {
                              search,
                              onCreate: savedSearch => {
                                  search.setSearchId(savedSearch.id);
                                  setLastSavedChecksum(search.searchChecksum);
                              },
                          });
                }}
            >
                {search.searchId
                    ? t('search.update_search', 'Update Search')
                    : t('search.save_search', 'Save Search')}
            </LoadingButton>
        </Box>
    );
}

import React from 'react';
import {
    AutocompleteState,
    createAutocomplete,
    GetSources,
} from '@algolia/autocomplete-core';
import {getSearchSuggestions, SearchSuggestion} from '../../../../api/asset.ts';
import {TSearchContext} from '../SearchContext.tsx';

export type UsedSuggest = {
    autocompleteState: AutocompleteState<SearchSuggestion>;
    autocomplete: ReturnType<typeof createAutocomplete<SearchSuggestion>>;
};

type Props = {
    search: TSearchContext;
    placeholder?: string;
};

export function useSuggest({search, placeholder}: Props): UsedSuggest {
    const {setInputQuery, inputQuery} = search;
    const queryValue = inputQuery.current || '';
    const [autocompleteState, setAutocompleteState] = React.useState<
        AutocompleteState<SearchSuggestion>
    >({} as AutocompleteState<SearchSuggestion>);

    const getSources = React.useCallback<GetSources<SearchSuggestion>>(() => {
        return [
            {
                sourceId: 'items',
                onSelect: ({item, setQuery}) => {
                    const newQuery = `"${item.name}"`;
                    setQuery(newQuery);
                    setInputQuery(newQuery);
                    search.setQuery(newQuery, true);
                },
                getItems({query}) {
                    return getSearchSuggestions(query).then(r => {
                        console.log('ES Debug', r.debug);
                        console.log('ES Query', JSON.stringify(r.debug.query));

                        return r.result;
                    });
                },
            },
        ];
    }, [search]);

    const autocomplete = React.useMemo(
        () =>
            createAutocomplete<SearchSuggestion>({
                onStateChange({state}) {
                    setAutocompleteState(state);
                },
                getSources,
                placeholder,
            }),
        []
    );

    React.useEffect(() => {
        autocomplete.setQuery(queryValue);
    }, [queryValue]);

    return {
        autocomplete,
        autocompleteState,
    };
}

import {useCallback} from 'react';
import {FieldValues} from 'react-hook-form';
import {
    AsyncRSelectProps,
    AsyncRSelectWidget,
    SelectOption,
} from '@alchemy/react-form';
import {SavedSearch} from '../../types.ts';
import {getSavedSearches} from '../../api/savedSearch.ts';
import {EntityName} from '../../api/types.ts';
import {getIri} from '@alchemy/api';

type Props<TFieldValues extends FieldValues> = {
    useIRI?: boolean;
} & AsyncRSelectProps<TFieldValues, false>;

export default function SavedSearchSelect<TFieldValues extends FieldValues>({
    useIRI,
    ...rest
}: Props<TFieldValues>) {
    const load = useCallback(
        async (inputValue: string): Promise<SelectOption[]> => {
            const data = await getSavedSearches(undefined, {
                query: inputValue,
            });

            return data.result
                .map((t: SavedSearch) => {
                    return {
                        value: useIRI
                            ? getIri(EntityName.SavedSearch, t.id)
                            : t.id,
                        label: t.title,
                    };
                })
                .filter(i =>
                    i.label
                        .toLowerCase()
                        .includes((inputValue || '').toLowerCase())
                );
        },
        []
    );

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'saved-searches'}
            {...rest}
            loadOptions={load}
        />
    );
}

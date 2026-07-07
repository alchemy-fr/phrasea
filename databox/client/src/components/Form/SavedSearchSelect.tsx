import {FieldValues} from 'react-hook-form';
import {AsyncRSelectProps, AsyncRSelectWidget} from '@alchemy/react-form';
import {getSavedSearches} from '../../api/savedSearch.ts';
import {EntityName} from '../../api/types.ts';
import {createIriFromId} from '@alchemy/api';
import {usePaginatedSelectLoader} from '../../hooks/usePaginatedSelectLoader.ts';

type Props<TFieldValues extends FieldValues> = {
    useIRI?: boolean;
} & AsyncRSelectProps<TFieldValues, false>;

export default function SavedSearchSelect<TFieldValues extends FieldValues>({
    useIRI,
    ...rest
}: Props<TFieldValues>) {
    const {loadOptions} = usePaginatedSelectLoader({
        load: props =>
            getSavedSearches({
                ...props,
            }),
        map: t => {
            return {
                value: useIRI
                    ? createIriFromId(EntityName.SavedSearch, t.id)
                    : t.id,
                label: t.name,
            };
        },
        filterLabels: true,
    });

    return (
        <AsyncRSelectWidget<TFieldValues>
            cacheId={'saved-searches'}
            {...rest}
            loadOptions={loadOptions}
        />
    );
}

import {NormalizedCollectionResponse} from '@alchemy/api';
import {GroupBase, SelectLoadOptions, SelectOption} from '@alchemy/react-form';
import {QueryAndPaginationParams} from '../api/types.ts';
import {useCallback} from 'react';

type Props<D extends object, Opt extends SelectOption> = {
    map: (data: D) => Opt | GroupBase<Opt>;
    load: (
        props: QueryAndPaginationParams
    ) => Promise<NormalizedCollectionResponse<D>>;
    filterLabels?: boolean;
    deps?: any[];
};

export function usePaginatedSelectLoader<
    D extends object,
    Opt extends SelectOption = SelectOption,
>({load, map, filterLabels, deps = []}: Props<D, Opt>) {
    const loadOptions = useCallback<SelectLoadOptions<Opt>>(
        async (inputValue, _loaded, additional) => {
            const data = await load({
                query: inputValue,
                nextUrl: additional?.nextUrl,
            });

            const options = data.result.map(map);
            return {
                options: filterLabels
                    ? options.filter(i =>
                          (i as Opt).label
                              .toLowerCase()
                              .includes((inputValue || '').toLowerCase())
                      )
                    : options,
                hasMore: !!data.next,
                additional: {nextUrl: data.next},
            };
        },
        // eslint-disable-next-line react-hooks/use-memo
        [...(deps ?? [])]
    );

    return {
        loadOptions,
    };
}

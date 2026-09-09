import {useCallback, useMemo} from 'react';
import {useSearch} from '../SearchProvider';
import {parseAQL} from '../aql/parser';
import {ConditionBuilder} from '../aql/serializer';
import type {ScalarValue} from '../aql/types';

/**
 * Facet names are attribute search slugs, optionally suffixed by a locale
 * (`description_fr`). The underlying field is what AQL expects.
 */
export function facetField(name: string): string {
    return name;
}

/**
 * Bridges a facet with the search condition it controls (one condition per
 * facet, identified by the facet name so it gets replaced instead of stacked).
 */
export function useFacetCondition(name: string) {
    const search = useSearch();
    const field = facetField(name);
    const condition = search.conditions.find(c => c.id === name);
    const active = !!condition && !condition.disabled;

    const builder = useMemo(
        () =>
            ConditionBuilder.fromQuery(
                field,
                active ? parseAQL(condition!.query) : undefined
            ),
        [field, active, condition]
    );

    const commit = useCallback(
        (b: ConditionBuilder) => {
            const query = b.toString();
            if (query === '') {
                search.removeCondition(name);
            } else {
                search.upsertCondition({id: name, query});
            }
        },
        [search, name]
    );

    return {
        condition,
        active,
        field,
        builder,
        hasValue: (value: ScalarValue) => active && builder.hasValue(value),
        toggleValue: (value: ScalarValue) => {
            const b = new ConditionBuilder(
                field,
                [...builder.getValues()],
                builder.includeMissing
            );
            commit(b.toggleValue(value));
        },
        toggleMissing: () => {
            const b = new ConditionBuilder(
                field,
                [...builder.getValues()],
                !builder.includeMissing
            );
            commit(b);
        },
        setRawQuery: (query: string) =>
            search.upsertCondition({id: name, query}),
        clear: () => search.removeCondition(name),
    };
}

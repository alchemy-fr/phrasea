import {useCallback, useMemo} from 'react';
import {useSearch} from '../SearchProvider';
import {parseAQL} from '../aql/parser';
import {ConditionBuilder} from '../aql/serializer';
import type {ScalarValue} from '../aql/types';
import {
    aqlKey,
    useDefinitionsBySearchSlug,
} from '@/features/attributes/definitionsStore';

/**
 * Facet names are attribute search slugs (`keywords_text_m`), optionally
 * suffixed by a locale (`description_text_s_fr`); AQL conditions use the
 * attribute slug instead (built-ins keep their `@name`).
 */
export function useFacetField(name: string): string {
    const bySearchSlug = useDefinitionsBySearchSlug();

    return useMemo(() => {
        const definition =
            bySearchSlug[name] ?? bySearchSlug[name.replace(/_[a-z]{2}$/, '')];

        return definition ? aqlKey(definition) : name;
    }, [bySearchSlug, name]);
}

/**
 * Bridges a facet with the search condition it controls (one condition per
 * facet, identified by the facet name so it gets replaced instead of stacked).
 */
export function useFacetCondition(name: string) {
    const search = useSearch();
    const field = useFacetField(name);
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

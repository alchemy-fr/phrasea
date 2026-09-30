import {useEffect, useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import type {AttributeDefinitionOrBuiltIn, Facets} from '@/types/api';
import {getSearchSuggestions} from '@/lib/api/assets';
import {useDefinitionsBySlug} from '@/features/attributes/definitionsStore';
import {debounce} from '@/lib/utils/misc';
import {
    apiItemToValueSuggestion,
    clientValueSuggestions,
    isApiSuggestable,
    isFieldToken,
    mergeValueSuggestions,
    parseSearchInput,
    rawSuggestionFor,
    suggestFields,
    SuggestionItem,
    ValueSuggestion,
} from './filterSuggestions';

const DEBOUNCE_MS = 200;

/**
 * Suggestions for the search input: fields to filter on and text terms while
 * typing a word, the values of the field once `field:` is typed. Client-side
 * items follow the input synchronously, only the API calls are debounced.
 */
export function useSearchSuggestions(
    input: string,
    {enabled, facets}: {enabled: boolean; facets?: Facets}
): {
    mode: 'text' | 'field';
    items: SuggestionItem[];
    fieldDefinition?: AttributeDefinitionOrBuiltIn;
    /** Value typed after `field:` */
    valuePrefix: string;
} {
    const {t} = useTranslation();
    const bySlug = useDefinitionsBySlug();
    const [debounced, setDebounced] = useState(input);
    const setDebouncedInput = useRef(
        debounce((q: string) => setDebounced(q), DEBOUNCE_MS)
    ).current;
    useEffect(() => {
        setDebouncedInput(input);

        return () => setDebouncedInput.cancel();
    }, [input, setDebouncedInput]);

    const parsed = useMemo(() => parseSearchInput(input), [input]);
    const debouncedParsed = useMemo(
        () => parseSearchInput(debounced),
        [debounced]
    );

    const fieldDefinition =
        parsed.mode === 'field'
            ? (bySlug[parsed.field] ?? bySlug[`@${parsed.field}`])
            : undefined;
    // The API is scoped on the definition of the debounced input, as long as
    // it is still the field being typed
    const debouncedFieldDefinition =
        debouncedParsed.mode === 'field'
            ? (bySlug[debouncedParsed.field] ??
              bySlug[`@${debouncedParsed.field}`])
            : undefined;
    const apiField = useMemo(
        () =>
            fieldDefinition &&
            debouncedParsed.mode === 'field' &&
            debouncedFieldDefinition === fieldDefinition &&
            isApiSuggestable(fieldDefinition)
                ? {
                      definition: fieldDefinition,
                      prefix: debouncedParsed.valuePrefix,
                  }
                : undefined,
        [fieldDefinition, debouncedFieldDefinition, debouncedParsed]
    );

    const textQuery =
        parsed.mode === 'text' && debouncedParsed.mode === 'text'
            ? debouncedParsed.text
            : '';

    const textSuggestions = useQuery({
        queryKey: ['suggest', textQuery],
        queryFn: ({signal}) => getSearchSuggestions(textQuery, {}, signal),
        enabled: enabled && textQuery.length > 0,
        staleTime: 30_000,
        select: r => r.items,
    });

    const valueSuggestions = useQuery({
        queryKey: [
            'suggest',
            'definition',
            apiField?.definition.id,
            apiField?.prefix,
        ],
        queryFn: ({signal}) =>
            getSearchSuggestions(
                apiField!.prefix,
                {definition: apiField!.definition.id},
                signal
            ),
        enabled: enabled && !!apiField,
        staleTime: 30_000,
        select: r => r.items,
    });

    const items = useMemo((): SuggestionItem[] => {
        if (!enabled) {
            return [];
        }
        if (parsed.mode === 'field') {
            if (!fieldDefinition) {
                return [];
            }
            const client = clientValueSuggestions({
                definition: fieldDefinition,
                prefix: parsed.valuePrefix,
                facets,
                t,
            });
            const api: ValueSuggestion[] = apiField
                ? (valueSuggestions.data ?? [])
                      .map(item =>
                          apiItemToValueSuggestion(item, fieldDefinition)
                      )
                      .filter((s): s is ValueSuggestion => s !== null)
                : [];
            const values = mergeValueSuggestions(client, api);
            const raw = rawSuggestionFor(
                fieldDefinition,
                parsed.valuePrefix,
                values
            );

            return raw ? [...values, raw] : values;
        }

        const fields = isFieldToken(parsed.text)
            ? suggestFields(parsed.text, Object.values(bySlug))
            : [];
        const texts: SuggestionItem[] =
            textQuery && textSuggestions.data
                ? textSuggestions.data.map(suggestion => ({
                      kind: 'text',
                      suggestion,
                  }))
                : [];

        return [...fields, ...texts];
    }, [
        enabled,
        parsed,
        fieldDefinition,
        facets,
        t,
        apiField,
        valueSuggestions.data,
        bySlug,
        textQuery,
        textSuggestions.data,
    ]);

    return {
        mode: parsed.mode,
        items,
        fieldDefinition,
        valuePrefix: parsed.mode === 'field' ? parsed.valuePrefix : '',
    };
}

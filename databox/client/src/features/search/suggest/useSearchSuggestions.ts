import {useEffect, useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import type {AttributeDefinitionOrBuiltIn, Facets} from '@/types/api';
import {
    useDefinitionsBySlug,
    useDefinitionsStore,
} from '@/features/attributes/definitionsStore';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {debounce} from '@/lib/utils/misc';
import {
    candidateToValueSuggestion,
    clientValueSuggestions,
    isFieldToken,
    mergeValueSuggestions,
    parseSearchInput,
    rawSuggestionFor,
    suggestFields,
    SuggestionItem,
    ValueSuggestion,
} from './filterSuggestions';
import {fetchRemoteValues, hasRemoteValues} from './remoteValues';
import {getSearchSuggestions} from '@/lib/api/assets';
import {AttributeType} from '@/types/api';

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
    const definitions = useDefinitionsStore(s => s.definitions);
    const workspaces = useCollectionStore(s => s.workspaces);
    const loadWorkspaces = useCollectionStore(s => s.loadWorkspaces);
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
    const debouncedFieldDefinition =
        debouncedParsed.mode === 'field'
            ? (bySlug[debouncedParsed.field] ??
              bySlug[`@${debouncedParsed.field}`])
            : undefined;

    // `@workspace:` lists the workspaces from the store
    const wantsWorkspaces =
        enabled && fieldDefinition?.type === AttributeType.Workspace;
    useEffect(() => {
        if (wantsWorkspaces) {
            void loadWorkspaces();
        }
    }, [wantsWorkspaces, loadWorkspaces]);

    // Remote values are fetched for the definition of the debounced input, as
    // long as it is still the field being typed
    const remote = useMemo(
        () =>
            fieldDefinition &&
            debouncedParsed.mode === 'field' &&
            debouncedFieldDefinition === fieldDefinition &&
            hasRemoteValues(fieldDefinition)
                ? {
                      definition: fieldDefinition,
                      definitionIds: fieldDefinition.builtIn
                          ? []
                          : definitions
                                .filter(d => d.slug === fieldDefinition.slug)
                                .map(d => d.id),
                      prefix: debouncedParsed.valuePrefix,
                  }
                : undefined,
        [
            fieldDefinition,
            debouncedFieldDefinition,
            debouncedParsed,
            definitions,
        ]
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

    const remoteValues = useQuery({
        queryKey: [
            'suggest',
            'values',
            remote?.definition.id,
            remote?.definitionIds,
            remote?.prefix,
        ],
        queryFn: ({signal}) => fetchRemoteValues({...remote!, signal}),
        enabled: enabled && !!remote,
        staleTime: 30_000,
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
                workspaces,
                t,
            });
            const api: ValueSuggestion[] = remote
                ? (remoteValues.data ?? [])
                      .map(candidate =>
                          candidateToValueSuggestion(fieldDefinition, candidate)
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
        workspaces,
        t,
        remote,
        remoteValues.data,
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

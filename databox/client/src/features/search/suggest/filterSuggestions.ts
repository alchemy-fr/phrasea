import type {TFunction} from 'i18next';
import {
    AQLQuery,
    AttributeDefinitionOrBuiltIn,
    AttributeType,
    Facet,
    Facets,
    FacetType,
    SearchSuggestion,
    Workspace,
} from '@/types/api';
import {aqlKey} from '@/features/attributes/definitionsStore';
import {
    assetStatusLabels,
    fileFamilyLabels,
    getAttributeType,
    privacyLabels,
} from '@/features/attributes/types/registry';
import {fileFamilies} from '@/lib/utils/mime';
import {AQLOperator, AQLValueExpr, RawType, ScalarValue} from '../aql/types';
import {conditionToString} from '../aql/serializer';
import {rawTypeMap, validateAST} from '../aql/validation';
import {parseAQL} from '../aql/parser';
import {resolveBucket} from '../facets/buckets';

/**
 * Filter suggestions of the search input: a field (`@isStory:`), then one of
 * its values, become an AQL condition (`@isStory IS true`).
 */

export type ParsedInput =
    | {mode: 'text'; text: string}
    | {mode: 'field'; field: string; valuePrefix: string};

export type FieldSuggestion = {
    kind: 'field';
    /** AQL key of the field (`@isStory`, `title`) */
    key: string;
    /** Text to insert in the input (`@isStory:`) */
    text: string;
    /** `text` with the matched part marked for <Highlight> */
    hl: string;
    definition: AttributeDefinitionOrBuiltIn;
};

export type ValueSuggestion = {
    kind: 'value';
    field: string;
    value: ScalarValue;
    label: string;
    hl?: string;
    /** The AQL condition applying this value */
    query: string;
    definition: AttributeDefinitionOrBuiltIn;
};

export type RawSuggestion = {
    kind: 'raw';
    field: string;
    text: string;
    query: string;
    definition: AttributeDefinitionOrBuiltIn;
};

export type TextSuggestion = {kind: 'text'; suggestion: SearchSuggestion};

export type SuggestionItem =
    | FieldSuggestion
    | ValueSuggestion
    | RawSuggestion
    | TextSuggestion;

const FIELD_INPUT = /^(@?[a-zA-Z_][\w-]*):(.*)$/;
const FIELD_TOKEN = /^@?[\w-]*$/;

export function parseSearchInput(value: string): ParsedInput {
    const m = FIELD_INPUT.exec(value.trimStart());
    if (m) {
        return {mode: 'field', field: m[1], valuePrefix: m[2].trim()};
    }

    return {mode: 'text', text: value.trim()};
}

/** A single word worth matching against field names (`@` alone lists the built-ins) */
export function isFieldToken(text: string): boolean {
    return FIELD_TOKEN.test(text) && (text === '@' || text.length >= 2);
}

/** Long texts: neither worth filtering on from the search bar nor suggesting values for */
const LONG_TEXT_TYPES: AttributeType[] = [
    AttributeType.Textarea,
    AttributeType.Html,
    AttributeType.Code,
    AttributeType.Json,
    AttributeType.WebVtt,
];

export function isLongTextField(d: AttributeDefinitionOrBuiltIn): boolean {
    return LONG_TEXT_TYPES.includes(d.type);
}

function isSuggestableField(d: AttributeDefinitionOrBuiltIn): boolean {
    return (
        d.enabled !== false &&
        (!!d.builtIn || d.searchable) &&
        !isLongTextField(d)
    );
}

function highlightKey(key: string, needle: string): string {
    const index = needle ? key.toLowerCase().indexOf(needle) : -1;
    if (index < 0) {
        return key;
    }

    return `${key.slice(0, index)}[hl]${key.slice(index, index + needle.length)}[/hl]${key.slice(index + needle.length)}`;
}

export function suggestFields(
    text: string,
    definitions: AttributeDefinitionOrBuiltIn[],
    limit = 8
): FieldSuggestion[] {
    const builtInOnly = text.startsWith('@');
    const needle = text.replace(/^@/, '').toLowerCase();
    const seen = new Set<string>();
    const ranked: {rank: number; item: FieldSuggestion}[] = [];

    for (const definition of definitions) {
        if (!isSuggestableField(definition)) {
            continue;
        }
        if (builtInOnly && !definition.builtIn) {
            continue;
        }
        const key = aqlKey(definition);
        if (seen.has(key)) {
            continue;
        }
        const bareKey = key.replace(/^@/, '').toLowerCase();
        const name = definition.displayName.toLowerCase();
        let rank: number;
        if (needle === '') {
            rank = 4;
        } else if (bareKey === needle) {
            rank = 0;
        } else if (bareKey.startsWith(needle)) {
            rank = 1;
        } else if (name.startsWith(needle)) {
            rank = 2;
        } else if (
            needle.length >= 2 &&
            (bareKey.includes(needle) || name.includes(needle))
        ) {
            rank = 3;
        } else {
            continue;
        }
        seen.add(key);
        ranked.push({
            rank,
            item: {
                kind: 'field',
                key,
                text: `${key}:`,
                hl: `${highlightKey(key, needle)}:`,
                definition,
            },
        });
    }

    ranked.sort(
        (a, b) =>
            a.rank - b.rank ||
            Number(!!b.item.definition.builtIn) -
                Number(!!a.item.definition.builtIn) ||
            a.item.definition.displayName.localeCompare(
                b.item.definition.displayName
            )
    );

    const items = ranked.map(r => r.item);

    return needle === '' ? items : items.slice(0, limit);
}

/** The field references entities (tags, workspaces, entity lists...): values are ids */
export function isEntityField(
    definition: AttributeDefinitionOrBuiltIn
): boolean {
    return !!getAttributeType(definition.type).entity;
}

/**
 * The API can suggest the indexed values of this attribute (see
 * AttributeTypeInterface::supportsSuggest() on the API side).
 */
export function isApiSuggestable(
    definition: AttributeDefinitionOrBuiltIn
): boolean {
    if (definition.builtIn || isLongTextField(definition)) {
        return false;
    }

    return [
        AttributeType.Text,
        AttributeType.Keyword,
        AttributeType.Color,
        AttributeType.Entity,
    ].includes(definition.type);
}

/** A value to suggest, before being turned into a condition */
export type Candidate = {value: ScalarValue; label: string; hl?: string};

function facetOf(
    definition: AttributeDefinitionOrBuiltIn,
    facets: Facets | undefined
): Facet | undefined {
    if (!facets) {
        return undefined;
    }
    const slug = definition.searchSlug;
    if (facets[slug]) {
        return facets[slug];
    }
    // Translatable text facets are suffixed by their locale (`desc_text_s_fr`)
    const name = Object.keys(facets).find(
        k => k.replace(/_[a-z]{2}$/, '') === slug
    );

    return name ? facets[name] : undefined;
}

function clientCandidates(
    definition: AttributeDefinitionOrBuiltIn,
    facets: Facets | undefined,
    workspaces: Workspace[] | undefined,
    t: TFunction
): Candidate[] {
    switch (definition.type) {
        case AttributeType.Workspace:
            if (workspaces?.length) {
                return workspaces.map(w => ({
                    value: w.id,
                    label: w.displayName ?? w.name,
                }));
            }
            break;
        case AttributeType.Boolean:
            return [
                {value: true, label: t('common.yes', 'Yes')},
                {value: false, label: t('common.no', 'No')},
            ];
        case AttributeType.Privacy:
            return Object.entries(privacyLabels(t)).map(([k, label]) => ({
                value: Number(k),
                label,
            }));
        case AttributeType.AssetStatus:
            return Object.entries(assetStatusLabels(t)).map(([k, label]) => ({
                value: Number(k),
                label,
            }));
        case AttributeType.FileFamily: {
            const labels = fileFamilyLabels(t);

            return fileFamilies.map(family => ({
                value: family,
                label: labels[family],
            }));
        }
    }

    const facet = facetOf(definition, facets);
    if (!facet) {
        return [];
    }
    const widget = facet.meta.widget;
    if (
        widget &&
        widget !== FacetType.Text &&
        widget !== FacetType.Entity &&
        widget !== FacetType.Boolean
    ) {
        return [];
    }

    return facet.buckets.map(bucket => {
        const {label, value} = resolveBucket(bucket);

        return {value: value as ScalarValue, label};
    });
}

function matchRank(candidate: Candidate, prefix: string): number {
    if (prefix === '') {
        return 0;
    }
    const haystacks = [
        candidate.label.toLowerCase(),
        String(candidate.value).toLowerCase(),
    ];
    if (haystacks.some(h => h.startsWith(prefix))) {
        return 0;
    }
    if (haystacks.some(h => h.includes(prefix))) {
        return 1;
    }

    return -1;
}

export function candidateToValueSuggestion(
    definition: AttributeDefinitionOrBuiltIn,
    candidate: Candidate
): ValueSuggestion | null {
    const query = buildFilterCondition(definition, candidate.value);
    if (query === null) {
        return null;
    }

    return {
        kind: 'value',
        field: aqlKey(definition),
        value: candidate.value,
        label: candidate.label,
        hl: candidate.hl,
        query,
        definition,
    };
}

/**
 * Values known on the client: booleans, fixed enumerations, the workspaces
 * and the facet buckets of the current results, filtered by the typed prefix.
 */
export function clientValueSuggestions({
    definition,
    prefix,
    facets,
    workspaces,
    t,
}: {
    definition: AttributeDefinitionOrBuiltIn;
    prefix: string;
    facets?: Facets;
    workspaces?: Workspace[];
    t: TFunction;
}): ValueSuggestion[] {
    const needle = prefix.trim().toLowerCase();

    return clientCandidates(definition, facets, workspaces, t)
        .map((candidate, index) => ({
            candidate,
            index,
            rank: matchRank(candidate, needle),
        }))
        .filter(c => c.rank >= 0)
        .sort((a, b) => a.rank - b.rank || a.index - b.index)
        .map(c => candidateToValueSuggestion(definition, c.candidate))
        .filter((s): s is ValueSuggestion => s !== null);
}

/** A value suggested by the API for this definition (`/assets/suggest?definition=`) */
export function apiItemToValueSuggestion(
    item: SearchSuggestion,
    definition: AttributeDefinitionOrBuiltIn
): ValueSuggestion | null {
    const entity = isEntityField(definition);
    if (entity && !item.entityId) {
        return null;
    }

    return candidateToValueSuggestion(definition, {
        value: entity ? item.entityId! : item.name,
        label: item.name,
        hl: item.hl,
    });
}

/** Client values first, then the API ones not already listed */
export function mergeValueSuggestions(
    client: ValueSuggestion[],
    api: ValueSuggestion[],
    limit = 10
): ValueSuggestion[] {
    const seen = new Set(client.map(s => String(s.value)));
    const merged = [...client];
    for (const s of api) {
        const key = String(s.value);
        if (!seen.has(key)) {
            seen.add(key);
            merged.push(s);
        }
    }

    return merged.slice(0, limit);
}

/**
 * The typed text as a value, for fields whose values are typed rather than
 * picked (text, numbers, dates); none when a suggested value already matches.
 */
export function rawSuggestionFor(
    definition: AttributeDefinitionOrBuiltIn,
    prefix: string,
    values: ValueSuggestion[]
): RawSuggestion | null {
    const text = prefix.trim();
    if (text === '' || isEntityField(definition)) {
        return null;
    }
    const raw = rawTypeMap[definition.type];
    if (
        !raw ||
        raw === RawType.Boolean ||
        raw === RawType.GeoPoint ||
        raw === RawType.Id
    ) {
        return null;
    }
    if (
        (raw === RawType.Date || raw === RawType.DateTime) &&
        Number.isNaN(Date.parse(text))
    ) {
        return null;
    }
    const lower = text.toLowerCase();
    if (
        values.some(
            v =>
                v.label.toLowerCase() === lower ||
                String(v.value).toLowerCase() === lower
        )
    ) {
        return null;
    }
    const query = buildFilterCondition(definition, text);
    if (query === null) {
        return null;
    }

    return {kind: 'raw', field: aqlKey(definition), text, query, definition};
}

/**
 * `field IS value` for the definition, typed after its raw type and validated;
 * `null` when the value does not fit the field.
 */
export function buildFilterCondition(
    definition: AttributeDefinitionOrBuiltIn,
    value: ScalarValue
): string | null {
    if (value === null) {
        return null;
    }
    const raw = rawTypeMap[definition.type];
    let rightOperand: AQLValueExpr;
    if (raw === RawType.Boolean) {
        rightOperand =
            typeof value === 'boolean' ? value : String(value) === 'true';
    } else if (raw === RawType.Number) {
        const n = typeof value === 'number' ? value : Number(value);
        if (typeof value === 'string' && value.trim() === '') {
            return null;
        }
        if (!Number.isFinite(n)) {
            return null;
        }
        rightOperand = n;
    } else {
        rightOperand = {literal: String(value)};
    }

    const key = aqlKey(definition);
    const query = conditionToString({
        leftOperand: {field: key},
        operator: AQLOperator.EQ,
        rightOperand,
    });
    try {
        const ast = parseAQL(query, true);
        if (!ast) {
            return null;
        }
        validateAST(ast, {[key]: definition});
    } catch {
        return null;
    }

    return query;
}

/**
 * Id of the condition to write for the field: the one facets use (the facet
 * name), so that the facet reflects the value and no second chip stacks up.
 * Translatable text facets are locale-suffixed: reuse an existing one.
 */
export function findConditionId(
    conditions: AQLQuery[],
    definition: AttributeDefinitionOrBuiltIn
): string {
    const slug = definition.searchSlug;
    if (definition.builtIn) {
        return slug;
    }
    const existing = conditions.find(
        c => c.id === slug || c.id.replace(/_[a-z]{2}$/, '') === slug
    );

    return existing?.id ?? slug;
}

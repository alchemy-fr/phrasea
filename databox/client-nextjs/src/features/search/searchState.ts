import type {AQLQuery, SortBy} from '@/types/api';

export enum BuiltInAttribute {
    Checksum = '@checksum',
    Collection = '@collection',
    CreatedAt = '@createdAt',
    DocUniqueId = '@docUniqueId',
    EditedAt = '@editedAt',
    FileExtension = '@extension',
    FileName = '@filename',
    HasSource = '@hasSource',
    FileSize = '@size',
    FileType = '@type',
    Id = '@id',
    Owner = '@owner',
    Privacy = '@privacy',
    Rendition = '@rendition',
    Score = '@score',
    Tag = '@tag',
    IsStory = '@isStory',
    Story = '@story',
    Workspace = '@workspace',
    Deleted = '@deleted',
    AssetStatus = '@assetStatus',
}

export type SearchState = {
    searchId?: string;
    query: string;
    conditions: AQLQuery[];
    sortBy: SortBy[];
    geolocation?: string;
};

export const emptySearchState: SearchState = {
    query: '',
    conditions: [],
    sortBy: [],
};

export const defaultSortBy: SortBy[] = [
    {a: BuiltInAttribute.Score, w: 1, g: false},
    {a: BuiltInAttribute.CreatedAt, w: 1, g: false},
];

export function resolveSortBy(sortBy: SortBy[]): SortBy[] {
    return sortBy.length > 0 ? sortBy : defaultSortBy;
}

export function isDefaultSortBy(sortBy: SortBy[]): boolean {
    return (
        sortBy.length === 0 ||
        (sortBy[0].a === BuiltInAttribute.Score &&
            sortBy[1]?.a === BuiltInAttribute.CreatedAt)
    );
}

/**
 * URL serialization: `?id=…&q=…&f=<id>[!][_]:<aql>&s=attr;w;g,…&l=lat,lng`
 */
const Param = {
    SearchId: 'id',
    Query: 'q',
    Condition: 'f',
    SortBy: 's',
    Geolocation: 'l',
} as const;

const Flag = {Inversed: '!', Disabled: '_'} as const;

function encodeSortBy(s: SortBy): string {
    return [s.a, String(s.w), s.g ? '1' : ''].join(';');
}

function decodeSortBy(str: string): SortBy {
    const [a, w, g] = str.split(';');

    return {a, w: w === '1' ? 1 : 0, g: g === '1'};
}

export function searchStateToParams(state: SearchState): URLSearchParams {
    const params = new URLSearchParams();
    if (state.searchId) {
        params.set(Param.SearchId, state.searchId);
    }
    if (state.query) {
        params.set(Param.Query, state.query);
    }
    for (const c of state.conditions) {
        params.append(
            Param.Condition,
            `${c.id}${c.inversed ? Flag.Inversed : ''}${c.disabled ? Flag.Disabled : ''}:${c.query}`
        );
    }
    if (state.sortBy.length > 0) {
        params.set(Param.SortBy, state.sortBy.map(encodeSortBy).join(','));
    }
    if (state.geolocation) {
        params.set(Param.Geolocation, state.geolocation);
    }

    return params;
}

export function paramsToSearchState(params: URLSearchParams): SearchState {
    const sort = params.get(Param.SortBy);

    return {
        searchId: params.get(Param.SearchId) ?? undefined,
        query: params.get(Param.Query) ?? '',
        conditions: params.getAll(Param.Condition).map(raw => {
            const sep = raw.indexOf(':');
            const head = sep === -1 ? raw : raw.slice(0, sep);
            const query = sep === -1 ? '' : raw.slice(sep + 1);
            const id = head.replace(/[!_]+$/, '');
            const flags = head.slice(id.length);

            return {
                id,
                query,
                disabled: flags.includes(Flag.Disabled) || undefined,
                inversed: flags.includes(Flag.Inversed) || undefined,
            };
        }),
        sortBy: sort ? sort.split(',').filter(Boolean).map(decodeSortBy) : [],
        geolocation: params.get(Param.Geolocation) ?? undefined,
    };
}

export function hasActiveSearch(state: SearchState): boolean {
    return Boolean(
        state.query ||
        state.conditions.length > 0 ||
        !isDefaultSortBy(state.sortBy) ||
        state.geolocation
    );
}

export function searchChecksum(state: SearchState): string {
    return JSON.stringify({
        q: state.query,
        c: state.conditions.filter(c => !c.disabled).map(c => c.query),
        s: resolveSortBy(state.sortBy),
        l: state.geolocation,
    });
}

export function upsertCondition(
    conditions: AQLQuery[],
    condition: AQLQuery
): AQLQuery[] {
    const index = conditions.findIndex(c => c.id === condition.id);
    if (index === -1) {
        return [...conditions, condition];
    }
    const next = [...conditions];
    next[index] = condition;

    return next;
}

export function removeConditions(
    conditions: AQLQuery[],
    ids: string[]
): AQLQuery[] {
    return conditions.filter(c => !ids.includes(c.id));
}

export function quoteAQL(value: string): string {
    return `"${value.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
}

import {SortBy} from './Filter';
import {AQLQueries, AQLQuery} from './AQL/query.ts';

const specSep = ';';
const arraySep = ',';

export enum BuiltInField {
    Collection = '@collection',
    CreatedAt = '@createdAt',
    EditedAt = '@editedAt',
    FileMimeType = '@mimetype',
    FileName = '@filename',
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
}

function encodeSortBy(sortBy: SortBy): string {
    return [sortBy.a, sortBy.w.toString(), sortBy.g ? '1' : ''].join(specSep);
}

function decodeSortBy(str: string): SortBy {
    const [a, w, g] = str.split(specSep);

    return {
        a,
        w: parseInt(w) as 0 | 1,
        g: g === '1',
    };
}

enum Flag {
    Inversed = '!',
    Disabled = '_',
}

enum SearchQueryParam {
    SearchId = 'id',
    Query = 'q',
    Condition = 'f',
    SortBy = 's',
    Geolocation = 'l',
}

export function queryToHash(
    searchId: string | undefined,
    query: string,
    conditions: AQLQueries,
    sortBy: SortBy[],
    geolocation: string | undefined
): string {
    const hashParts: string[] = [];
    if (searchId) {
        hashParts.push(
            `${SearchQueryParam.SearchId}=${encodeURIComponent(searchId)}`
        );
    }
    if (query) {
        hashParts.push(
            `${SearchQueryParam.Query}=${encodeURIComponent(query)}`
        );
    }
    if (conditions && conditions.length > 0) {
        hashParts.push(
            conditions
                .map(
                    q =>
                        `${SearchQueryParam.Condition}=${encodeURIComponent(q.id)}${q.inversed ? Flag.Inversed : ''}${q.disabled ? Flag.Disabled : ''}:${encodeURIComponent(q.query)}`
                )
                .join('&')
        );
    }
    if (sortBy && sortBy.length > 0) {
        hashParts.push(
            `${SearchQueryParam.SortBy}=${encodeURIComponent(
                sortBy.map(encodeSortBy).join(arraySep)
            )}`
        );
    }
    if (geolocation) {
        hashParts.push(
            `${SearchQueryParam.Geolocation}=${encodeURIComponent(geolocation)}`
        );
    }

    return hashParts.join('&');
}

export function hashToQuery(hash: string): {
    searchId: string | undefined;
    query: string;
    conditions: AQLQuery[];
    sortBy: SortBy[];
    geolocation: string | undefined;
} {
    const params = new URLSearchParams(hash.substring(1));
    const searchId = params.get(SearchQueryParam.SearchId);
    const sortBy = params.get(SearchQueryParam.SortBy);
    const geoLoc = params.get(SearchQueryParam.Geolocation);

    return {
        searchId: searchId ? decodeURIComponent(searchId) : undefined,
        query: decodeURIComponent(params.get(SearchQueryParam.Query) || ''),
        conditions: params.has(SearchQueryParam.Condition)
            ? params.getAll(SearchQueryParam.Condition).map(q => {
                  const [id, ...query] = q.split(':');
                  const field = id.replace(/[!_]$/, '');
                  const flags = id.substring(field.length);

                  return {
                      query: query.join(':'),
                      id: id.replace(/[!_]$/, ''),
                      disabled: flags.includes(Flag.Disabled),
                      inversed: flags.includes(Flag.Inversed),
                  } as AQLQuery;
              })
            : [],
        sortBy: sortBy
            ? decodeURIComponent(sortBy as string)
                  .split(arraySep)
                  .map(decodeSortBy)
            : [],
        geolocation: geoLoc ? geoLoc : undefined,
    };
}

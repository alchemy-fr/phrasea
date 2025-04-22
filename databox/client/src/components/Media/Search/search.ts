import {SortBy} from './Filter';
import {AQLQueries, AQLQuery} from './AQL/query.ts';

const specSep = ';';
const arraySep = ',';

export enum BuiltInFilter {
    Collection = '@collection',
    Privacy = '@privacy',
    Workspace = '@workspace',
    Tag = '@tag',
    CreatedAt = '@createdAt',
    EditedAt = '@editedAt',
    Score = '@score',
    FileType = '@type',
    FileMimeType = '@mimetype',
    FileSize = '@size',
    FileName = '@filename',
}

function encodeSortBy(sortBy: SortBy): string {
    return [
        sortBy.a,
        sortBy.w.toString(),
        sortBy.g ? '1' : '',
    ].join(specSep);
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

export function queryToHash(
    query: string,
    conditions: AQLQueries,
    sortBy: SortBy[],
    geolocation: string | undefined
): string {
    let hash = '';
    if (query) {
        hash += `q=${encodeURIComponent(query)}`;
    }
    if (conditions && conditions.length > 0) {
        hash += `${hash ? '&' : ''}${conditions
            .map(
                q =>
                    `f=${q.id}${q.inversed ? Flag.Inversed : ''}${q.disabled ? Flag.Disabled : ''}:${encodeURIComponent(q.query)}`
            )
            .join('&')}`;
    }
    if (sortBy && sortBy.length > 0) {
        hash += `${hash ? '&' : ''}s=${encodeURIComponent(
            sortBy.map(encodeSortBy).join(arraySep)
        )}`;
    }
    if (geolocation) {
        hash += `${hash ? '&' : ''}l=${encodeURIComponent(geolocation)}`;
    }

    return hash;
}

export function hashToQuery(hash: string): {
    query: string;
    conditions: AQLQuery[];
    sortBy: SortBy[];
    geolocation: string | undefined;
} {
    const params = new URLSearchParams(hash.substring(1));

    return {
        query: decodeURIComponent(params.get('q') || ''),
        conditions: params.has('f')
            ? params.getAll('f').map(q => {
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
        sortBy: params.get('s')
            ? decodeURIComponent(params.get('s') as string)
                  .split(arraySep)
                  .map(decodeSortBy)
            : [],
        geolocation: params.get('l')
            ? decodeURIComponent(params.get('l') as string)
            : undefined,
    };
}

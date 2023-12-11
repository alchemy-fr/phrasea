import React, {PropsWithChildren, useCallback, useState} from 'react';
import {SearchContext, TSearchContext} from './SearchContext';
import {
    extractLabelValueFromKey,
    FacetType,
    LabelledBucketValue,
    ResolvedBucketValue,
} from '../Asset/Facets';
import {FilterEntry, Filters, FilterType, SortBy} from './Filter';
import {BuiltInFilter, hashToQuery, queryToHash} from './search';
import useHash from '../../../lib/useHash';

export function getResolvedSortBy(sortBy: SortBy[]): SortBy[] {
    return sortBy.length > 0
        ? sortBy
        : [
              {
                  a: BuiltInFilter.Score,
                  t: 'Relevance',
                  w: 1,
                  g: false,
              },
              {
                  a: BuiltInFilter.CreatedAt,
                  t: 'Date Added',
                  w: 1,
                  g: false,
              },
          ];
}

export default function SearchProvider({children}: PropsWithChildren<{}>) {
    const [hash, setHash] = useHash();
    const [reloadInc, setReloadInc] = useState(0);
    const {query, filters, sortBy, geolocation} = hashToQuery(hash);
    const resolvedSortBy = getResolvedSortBy(sortBy);

    const setAttrFilters = useCallback(
        (handler: (prev: Filters) => Filters): boolean => {
            return setHash(
                queryToHash(query, handler(filters), sortBy, geolocation)
            );
        },
        [setHash, query, filters, sortBy, geolocation]
    );

    const selectWorkspace = useCallback<TSearchContext['selectWorkspace']>(
        (workspaceId, title, forceReload): void => {
            if (
                !setAttrFilters(p => {
                    const next = p.filter(
                        f =>
                            !(
                                [
                                    BuiltInFilter.Workspace,
                                    BuiltInFilter.Collection,
                                ] as string[]
                            ).includes(f.a)
                    );
                    if (!workspaceId) {
                        return next;
                    }

                    return next.concat([
                        {
                            a: BuiltInFilter.Workspace,
                            t: 'Workspaces',
                            v: [
                                {
                                    label: title!,
                                    value: workspaceId,
                                },
                            ],
                        },
                    ]);
                }) &&
                forceReload
            ) {
                setReloadInc(p => p + 1);
            }
        },
        [setAttrFilters]
    );

    const selectCollection = useCallback<TSearchContext['selectCollection']>(
        (absolutePath, title, forceReload): void => {
            if (
                !setAttrFilters(p => {
                    const next = p.filter(
                        f =>
                            !(
                                [
                                    BuiltInFilter.Workspace,
                                    BuiltInFilter.Collection,
                                ] as string[]
                            ).includes(f.a)
                    );
                    if (!absolutePath) {
                        return next;
                    }

                    return next.concat([
                        {
                            a: BuiltInFilter.Collection,
                            t: 'Collections',
                            v: [
                                {
                                    label: title!,
                                    value: '/' + absolutePath,
                                },
                            ],
                        },
                    ]);
                }) &&
                forceReload
            ) {
                setReloadInc(p => p + 1);
            }
        },
        [setAttrFilters]
    );

    const setSortBy = useCallback<TSearchContext['setSortBy']>(
        (newValue): void => {
            setHash(queryToHash(query, filters, newValue, geolocation));
        },
        [setHash, query, filters, geolocation]
    );

    const setQuery = useCallback(
        (handler: string | ((prev: string) => string)): void => {
            if (
                !setHash(
                    queryToHash(
                        typeof handler === 'string' ? handler : handler(query),
                        filters,
                        sortBy,
                        geolocation
                    )
                )
            ) {
                setReloadInc(p => p + 1);
            }
        },
        [setHash, query, filters, sortBy, geolocation]
    );

    const setGeoLocation = React.useCallback(
        (position: string | undefined) => {
            setHash(queryToHash(query, filters, sortBy, position));
        },
        [setHash, query, filters, sortBy, geolocation]
    );

    const removeAttrFilter = (key: number): void => {
        setAttrFilters(prev => {
            const f = [...prev];
            f.splice(key, 1);

            return f;
        });
    };

    const invertAttrFilter = (key: number): void => {
        setAttrFilters(prev => {
            const f = [...prev];

            if (f[key].i) {
                delete f[key].i;
            } else {
                f[key].i = 1;
            }

            return f;
        });
    };

    const toggleAttrFilter = (
        attrName: string,
        type: FilterType | undefined,
        keyValue: ResolvedBucketValue,
        attrTitle: string
    ): void => {
        setAttrFilters(prev => {
            const f = [...prev];

            const key = f.findIndex(_f => _f.a === attrName && !_f.i);

            if (key >= 0) {
                const {value} = extractLabelValueFromKey(keyValue, type);

                const tf = f[key];
                if (
                    tf.v.find(
                        v => extractLabelValueFromKey(v, type).value === value
                    )
                ) {
                    if (tf.v.length === 1) {
                        f.splice(key, 1);
                    } else {
                        tf.v = tf.v.filter(
                            v =>
                                extractLabelValueFromKey(v, type).value !==
                                value
                        );
                    }
                } else {
                    tf.v = tf.v.concat(keyValue);
                }
            } else {
                f.push({
                    t: attrTitle,
                    a: attrName,
                    v: [keyValue],
                    x: type,
                });
            }

            return f;
        });
    };

    const setAttrFilter = (
        attrName: string,
        type: FilterType | undefined,
        values: ResolvedBucketValue[],
        attrTitle: string,
        widget?: FacetType
    ): void => {
        setAttrFilters(prev => {
            const f = [...prev];

            const key = f.findIndex(_f => _f.a === attrName);

            if (key >= 0) {
                f[key].v = values;
            } else {
                const items: FilterEntry = {
                    t: attrTitle,
                    a: attrName,
                    v: values,
                    w: widget,
                    x: type,
                };
                f.push(items);
            }

            return f;
        });
    };

    const collections = filters
        .filter(f => f.a === BuiltInFilter.Collection && !f.i)
        .map(f => f.v as LabelledBucketValue[])
        .flat()
        .map((v: LabelledBucketValue) => v.value) as string[];
    const workspaces = filters
        .filter(f => f.a === BuiltInFilter.Workspace && !f.i)
        .map(f => f.v as LabelledBucketValue[])
        .flat()
        .map((v: LabelledBucketValue) => v.value) as string[];
    return (
        <SearchContext.Provider
            value={{
                selectWorkspace,
                selectCollection,
                collections,
                workspaces,
                toggleAttrFilter,
                setAttrFilter,
                invertAttrFilter,
                removeAttrFilter,
                attrFilters: filters,
                query,
                setQuery,
                searchChecksum: JSON.stringify({
                    query,
                    filters,
                    sortBy: resolvedSortBy,
                    geolocation,
                }),
                reloadInc,
                sortBy,
                setSortBy,
                geolocation,
                setGeoLocation,
            }}
        >
            {children}
        </SearchContext.Provider>
    );
}

import React, {PropsWithChildren, useCallback, useState} from 'react';
import {SearchContext, TSearchContext} from './SearchContext';
import {SortBy} from './Filter';
import {BuiltInField, hashToQuery, queryToHash} from './search';
import useHash from '../../../lib/useHash';
import {
    AQLQuery,
    AQLQueries,
    isAQLCondition,
    isAQLField,
    resolveAQLValue,
    generateQueryId,
} from './AQL/query.ts';
import {parseAQLQuery} from './AQL/AQL.ts';
import {AQLCondition, AQLQueryAST} from './AQL/aqlTypes.ts';
import {SavedSearch} from '../../../types.ts';
import {extractSearchData} from '../../../api/savedSearch.ts';

export function getResolvedSortBy(sortBy: SortBy[]): SortBy[] {
    return sortBy.length > 0
        ? sortBy
        : [
              {
                  a: BuiltInField.Score,
                  w: 1,
                  g: false,
              },
              {
                  a: BuiltInField.CreatedAt,
                  w: 1,
                  g: false,
              },
          ];
}

export default function SearchProvider({children}: PropsWithChildren<{}>) {
    const [hash, setHash] = useHash();
    const [reloadInc, setReloadInc] = useState(0);
    const {searchId, query, conditions, sortBy, geolocation} =
        hashToQuery(hash);
    const inputQuery = React.useRef<string>('');

    const setInputQuery = React.useCallback(
        (query: string) => {
            inputQuery.current = query;
        },
        [inputQuery]
    );

    const resolvedSortBy = getResolvedSortBy(sortBy);

    React.useEffect(() => {
        setInputQuery(query);
    }, [query]);

    const setConditions = useCallback(
        (
            handler: (prev: AQLQueries) => AQLQueries,
            newQuery?: string
        ): boolean => {
            return setHash(
                queryToHash(
                    searchId,
                    newQuery ?? inputQuery.current ?? query,
                    handler(conditions),
                    sortBy,
                    geolocation
                )
            );
        },
        [setHash, searchId, query, conditions, sortBy, geolocation]
    );

    const setSearchId = useCallback(
        (newSearchId?: string): boolean => {
            return setHash(
                queryToHash(newSearchId, query, conditions, sortBy, geolocation)
            );
        },
        [setHash, query, conditions, sortBy, geolocation]
    );

    const loadSearch = useCallback(
        (savedSearch: SavedSearch): boolean => {
            const payload = extractSearchData(savedSearch.data);

            return setHash(
                queryToHash(
                    savedSearch.id,
                    payload.query,
                    payload.conditions,
                    payload.sortBy,
                    geolocation
                )
            );
        },
        [setHash, geolocation]
    );

    const reset = useCallback((): boolean => {
        return setHash('');
    }, [setHash]);

    function replaceConditionHelper(
        conditions: AQLQueries,
        condition: AQLQuery
    ): AQLQueries {
        const expr = conditions.map(c =>
            c.id === condition.id ? condition : c
        );

        if (!expr.some(c => c.id === condition.id)) {
            expr.push(condition);
        }

        return expr;
    }

    function removeConditionsHelper(
        conditions: AQLQueries,
        ids: string[]
    ): AQLQueries {
        return conditions.filter(c => !ids.includes(c.id));
    }

    const selectWorkspace = useCallback<TSearchContext['selectWorkspace']>(
        (workspaceId, _title, forceReload): void => {
            if (
                !setConditions(p => {
                    const newConditions = removeConditionsHelper(p, [
                        BuiltInField.Collection,
                        BuiltInField.Deleted,
                    ]);

                    if (!workspaceId) {
                        return removeConditionsHelper(newConditions, [
                            BuiltInField.Workspace,
                        ]);
                    }

                    return replaceConditionHelper(newConditions, {
                        id: BuiltInField.Workspace,
                        query: `${BuiltInField.Workspace} = "${workspaceId}"`,
                    });
                }) &&
                forceReload
            ) {
                setReloadInc(p => p + 1);
            }
        },
        [setConditions]
    );

    const selectCollection = useCallback<TSearchContext['selectCollection']>(
        (collectionId, _title, forceReload): void => {
            if (
                !setConditions(p => {
                    const newConditions = removeConditionsHelper(p, [
                        BuiltInField.Workspace,
                        BuiltInField.Deleted,
                    ]);

                    if (!collectionId) {
                        return removeConditionsHelper(newConditions, [
                            BuiltInField.Collection,
                        ]);
                    }

                    return replaceConditionHelper(newConditions, {
                        id: BuiltInField.Collection,
                        query: `${BuiltInField.Collection} = "${collectionId}"`,
                    });
                }) &&
                forceReload
            ) {
                setReloadInc(p => p + 1);
            }
        },
        [setConditions]
    );

    const setSortBy = useCallback<TSearchContext['setSortBy']>(
        (newValue): void => {
            setHash(
                queryToHash(searchId, query, conditions, newValue, geolocation)
            );
        },
        [setHash, searchId, query, conditions, geolocation]
    );

    const setQuery = useCallback(
        (handler: string | ((prev: string) => string)): void => {
            if (
                !setHash(
                    queryToHash(
                        searchId,
                        typeof handler === 'string' ? handler : handler(query),
                        conditions,
                        sortBy,
                        geolocation
                    )
                )
            ) {
                setReloadInc(p => p + 1);
            }
        },
        [setHash, searchId, query, conditions, sortBy, geolocation]
    );

    const setGeoLocation = React.useCallback(
        (position: string | undefined) => {
            setHash(queryToHash(searchId, query, conditions, sortBy, position));
        },
        [setHash, searchId, query, conditions, sortBy, geolocation]
    );

    const upsertCondition = (condition: AQLQuery): void => {
        setConditions(prev => {
            const f = [...prev];

            const key = f.findIndex(_f => _f.id === condition.id);

            if (condition.renewId) {
                condition.id = generateQueryId();
                delete condition.renewId;
            }

            if (key >= 0) {
                f[key] = condition;
            } else {
                f.push(condition);
            }

            return f;
        });
    };

    const removeCondition = (condition: AQLQuery): void => {
        setConditions(prev => prev.filter(c => c.id !== condition.id));
    };

    const resetWithCondition = (condition: AQLQuery): void => {
        setConditions(_p => [condition]);
    };

    const conditionsAst = (
        conditions
            .filter(q => !q.disabled)
            .map(c => parseAQLQuery(c.query))
            .filter(q => q && isAQLCondition(q.expression)) as AQLQueryAST[]
    ).map(q => q.expression) as AQLCondition[];

    function filterOfType(type: BuiltInField): string[] {
        return conditionsAst
            .filter(
                c =>
                    isAQLField(c.leftOperand) &&
                    c.leftOperand.field === `${type}` &&
                    c.rightOperand
            )
            .map(c => {
                if (Array.isArray(c.rightOperand)) {
                    return c.rightOperand.map(o => resolveAQLValue(o));
                } else {
                    return [resolveAQLValue(c.rightOperand!)];
                }
            })
            .flat() as string[];
    }

    const workspaces = filterOfType(BuiltInField.Workspace);
    const collections = filterOfType(BuiltInField.Collection);

    return (
        <SearchContext.Provider
            value={{
                searchId,
                setSearchId,
                loadSearch,
                selectWorkspace,
                selectCollection,
                collections,
                workspaces,
                removeCondition,
                resetWithCondition,
                upsertCondition,
                conditions,
                query,
                setQuery,
                inputQuery,
                setInputQuery,
                searchChecksum: JSON.stringify({
                    query,
                    conditions,
                    sortBy: resolvedSortBy,
                    geolocation,
                }),
                reloadInc,
                sortBy,
                reset,
                setSortBy,
                geolocation,
                setGeoLocation,
            }}
        >
            {children}
        </SearchContext.Provider>
    );
}

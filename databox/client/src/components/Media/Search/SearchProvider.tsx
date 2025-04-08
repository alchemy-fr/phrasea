import React, {PropsWithChildren, useCallback, useState} from 'react';
import {SearchContext, TSearchContext} from './SearchContext';
import {SortBy} from './Filter';
import {hashToQuery, queryToHash} from './search';
import useHash from '../../../lib/useHash';
import {useTranslation} from 'react-i18next';
import type {TFunction} from '@alchemy/i18n';
import {
    AQLQuery,
    AQLQueries,
    isAQLCondition,
    isAQLField,
    resolveAQLValue,
} from './AQL/query.ts';
import {InternalKey, parseAQLQuery} from './AQL/AQL.ts';
import {AQLCondition, AQLQueryAST} from './AQL/aqlTypes.ts';

export function getResolvedSortBy(sortBy: SortBy[], t: TFunction): SortBy[] {
    return sortBy.length > 0
        ? sortBy
        : [
              {
                  a: `@${InternalKey.Score}`,
                  t: t('get_resolved_sort_by.relevance', `Relevance`),
                  w: 1,
                  g: false,
              },
              {
                  a: `@${InternalKey.CreatedAt}`,
                  t: t('get_resolved_sort_by.date_added', `Date Added`),
                  w: 1,
                  g: false,
              },
          ];
}

export default function SearchProvider({children}: PropsWithChildren<{}>) {
    const {t} = useTranslation();
    const [hash, setHash] = useHash();
    const [reloadInc, setReloadInc] = useState(0);
    const {query, conditions, sortBy, geolocation} = hashToQuery(hash);
    const inputQuery = React.useRef<string>('');

    const setInputQuery = React.useCallback(
        (query: string) => {
            inputQuery.current = query;
        },
        [inputQuery]
    );

    const resolvedSortBy = getResolvedSortBy(sortBy, t);

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
                    newQuery ?? inputQuery.current ?? query,
                    handler(conditions),
                    sortBy,
                    geolocation
                )
            );
        },
        [setHash, query, conditions, sortBy, geolocation]
    );

    const reset = useCallback((): boolean => {
        return setHash('');
    }, [setHash, query, conditions, sortBy, geolocation]);

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

    function removeConditionHelper(
        conditions: AQLQueries,
        id: string
    ): AQLQueries {
        return conditions.filter(c => c.id !== id);
    }

    const selectWorkspace = useCallback<TSearchContext['selectWorkspace']>(
        (workspaceId, _title, forceReload): void => {
            if (
                !setConditions(p => {
                    const newConditions = removeConditionHelper(
                        p,
                        InternalKey.Collection
                    );

                    if (!workspaceId) {
                        return removeConditionHelper(
                            newConditions,
                            InternalKey.Workspace
                        );
                    }

                    return replaceConditionHelper(newConditions, {
                        id: InternalKey.Workspace,
                        query: `@${InternalKey.Workspace} = "${workspaceId}"`,
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
                    const newConditions = removeConditionHelper(
                        p,
                        InternalKey.Workspace
                    );

                    if (!collectionId) {
                        return removeConditionHelper(
                            newConditions,
                            InternalKey.Collection
                        );
                    }

                    return replaceConditionHelper(newConditions, {
                        id: InternalKey.Collection,
                        query: `@${InternalKey.Collection} = "${collectionId}"`,
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
            setHash(queryToHash(query, conditions, newValue, geolocation));
        },
        [setHash, query, conditions, geolocation]
    );

    const setQuery = useCallback(
        (handler: string | ((prev: string) => string)): void => {
            if (
                !setHash(
                    queryToHash(
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
        [setHash, query, conditions, sortBy, geolocation]
    );

    const setGeoLocation = React.useCallback(
        (position: string | undefined) => {
            setHash(queryToHash(query, conditions, sortBy, position));
        },
        [setHash, query, conditions, sortBy, geolocation]
    );

    const upsertCondition = (condition: AQLQuery): void => {
        setConditions(prev => {
            const f = [...prev];

            const key = f.findIndex(_f => _f.id === condition.id);

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

    const conditionsAst = (
        conditions
            .filter(q => !q.disabled)
            .map(c => parseAQLQuery(c.query))
            .filter(q => q && isAQLCondition(q.expression)) as AQLQueryAST[]
    ).map(q => q.expression) as AQLCondition[];

    function filterOfType(type: InternalKey): string[] {
        return conditionsAst
            .filter(
                c =>
                    isAQLField(c.leftOperand) &&
                    c.leftOperand.field === `@${type}` &&
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

    const workspaces = filterOfType(InternalKey.Workspace);
    const collections = filterOfType(InternalKey.Collection);

    return (
        <SearchContext.Provider
            value={{
                selectWorkspace,
                selectCollection,
                collections,
                workspaces,
                removeCondition,
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

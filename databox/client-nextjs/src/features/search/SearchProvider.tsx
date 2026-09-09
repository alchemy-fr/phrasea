'use client';

import {
    createContext,
    PropsWithChildren,
    useCallback,
    useContext,
    useMemo,
    useRef,
    useState,
} from 'react';
import {usePathname, useRouter, useSearchParams} from 'next/navigation';
import type {
    AQLQuery,
    Collection,
    SavedSearch,
    SortBy,
    Workspace,
} from '@/types/api';
import {
    BuiltInAttribute,
    hasActiveSearch,
    paramsToSearchState,
    quoteAQL,
    removeConditions,
    resolveSortBy,
    searchChecksum,
    SearchState,
    searchStateToParams,
    upsertCondition,
} from './searchState';
import {parseAQL} from './aql/parser';
import {resolveScalar} from './aql/serializer';
import {isCondition, isField} from './aql/types';
import {useEntitiesStore} from './entitiesStore';
import {shortId} from '@/lib/utils/misc';

export type SearchContextValue = SearchState & {
    /** Stable checksum of the effective search (undefined when nothing is searched) */
    checksum: string;
    reloadInc: number;
    hasSearch: boolean;
    /** ids of collections / workspaces currently filtered */
    collections: string[];
    workspaces: string[];
    /** query typed in the search input but not submitted yet */
    inputQuery: React.RefObject<string>;
    setInputQuery: (q: string) => void;
    setQuery: (query: string) => void;
    setSortBy: (sortBy: SortBy[]) => void;
    setGeolocation: (position: string | undefined) => void;
    upsertCondition: (condition: AQLQuery & {renewId?: boolean}) => void;
    removeCondition: (id: string) => void;
    resetWithCondition: (condition: AQLQuery) => void;
    selectWorkspace: (id: string | undefined, workspace?: Workspace) => void;
    selectCollection: (id: string | undefined, collection?: Collection) => void;
    loadSavedSearch: (savedSearch: SavedSearch) => void;
    setSearchId: (id: string | undefined) => void;
    reset: () => void;
    reload: () => void;
};

const SearchContext = createContext<SearchContextValue | null>(null);

export function SearchProvider({
    children,
    basePath = '/assets',
}: PropsWithChildren<{basePath?: string}>) {
    const router = useRouter();
    const pathname = usePathname();
    const searchParams = useSearchParams();
    const [reloadInc, setReloadInc] = useState(0);
    const inputQuery = useRef<string>('');
    const storeEntity = useEntitiesStore(s => s.store);

    const state = useMemo(
        () => paramsToSearchState(searchParams),
        [searchParams]
    );

    const stateRef = useRef(state);
    stateRef.current = state;
    if (inputQuery.current !== state.query && !inputQuery.current) {
        inputQuery.current = state.query;
    }

    const commit = useCallback(
        (next: SearchState): boolean => {
            const params = searchStateToParams(next);
            const qs = params.toString();
            const currentQs = searchStateToParams(stateRef.current).toString();
            if (qs === currentQs) {
                return false;
            }
            // Navigating from a modal route back to the search page keeps the
            // current pathname when we are already on the search screen.
            const target = pathname.startsWith(basePath) ? pathname : basePath;
            router.push(`${target}${qs ? `?${qs}` : ''}`, {scroll: false});

            return true;
        },
        [router, pathname, basePath]
    );

    const update = useCallback(
        (
            patch:
                | Partial<SearchState>
                | ((prev: SearchState) => Partial<SearchState>)
        ) => {
            const prev = stateRef.current;
            const p = typeof patch === 'function' ? patch(prev) : patch;

            return commit({...prev, ...p});
        },
        [commit]
    );

    const setQuery = useCallback(
        (query: string) => {
            inputQuery.current = query;
            if (!update({query})) {
                setReloadInc(i => i + 1);
            }
        },
        [update]
    );

    const selectFilter = useCallback(
        (
            attribute: BuiltInAttribute.Workspace | BuiltInAttribute.Collection,
            id: string | undefined
        ) => {
            update(prev => {
                let conditions = removeConditions(prev.conditions, [
                    BuiltInAttribute.Workspace,
                    BuiltInAttribute.Collection,
                    BuiltInAttribute.Deleted,
                    BuiltInAttribute.AssetStatus,
                ]);
                if (id) {
                    conditions = upsertCondition(conditions, {
                        id: attribute,
                        query: `${attribute} = ${quoteAQL(id)}`,
                    });
                }

                return {conditions};
            });
        },
        [update]
    );

    const value = useMemo<SearchContextValue>(() => {
        const enabledAsts = state.conditions
            .filter(c => !c.disabled)
            .map(c => parseAQL(c.query)?.expression)
            .filter(e => e && isCondition(e));

        const idsOf = (field: string): string[] =>
            enabledAsts.flatMap(expr => {
                if (
                    !expr ||
                    !isCondition(expr) ||
                    !isField(expr.leftOperand) ||
                    expr.leftOperand.field !== field
                ) {
                    return [];
                }
                const right = expr.rightOperand;
                if (right === undefined) {
                    return [];
                }
                const list = Array.isArray(right) ? right : [right];

                return list
                    .map(v => {
                        try {
                            return resolveScalar(v);
                        } catch {
                            return null;
                        }
                    })
                    .filter((v): v is string => typeof v === 'string');
            });

        const hasSearch = hasActiveSearch(state);

        return {
            ...state,
            checksum: searchChecksum(state),
            reloadInc,
            hasSearch,
            collections: idsOf(BuiltInAttribute.Collection),
            workspaces: idsOf(BuiltInAttribute.Workspace),
            inputQuery,
            setInputQuery: q => {
                inputQuery.current = q;
            },
            setQuery,
            setSortBy: sortBy => update({sortBy}),
            setGeolocation: geolocation => update({geolocation}),
            upsertCondition: condition => {
                const {renewId, ...c} = condition;
                update(prev => ({
                    conditions: upsertCondition(
                        prev.conditions,
                        renewId ? {...c, id: shortId()} : c
                    ),
                }));
            },
            removeCondition: id =>
                update(prev => ({
                    conditions: prev.conditions.filter(c => c.id !== id),
                })),
            resetWithCondition: condition =>
                update({
                    conditions: [condition],
                    query: '',
                    searchId: undefined,
                }),
            selectWorkspace: (id, workspace) => {
                if (workspace) {
                    storeEntity(workspace['@id'], workspace as any);
                }
                selectFilter(BuiltInAttribute.Workspace, id);
            },
            selectCollection: (id, collection) => {
                if (collection) {
                    storeEntity(collection['@id'], collection as any);
                }
                selectFilter(BuiltInAttribute.Collection, id);
            },
            loadSavedSearch: saved => {
                inputQuery.current = saved.data.query ?? '';
                commit({
                    searchId: saved.id,
                    query: saved.data.query ?? '',
                    conditions: saved.data.conditions ?? [],
                    sortBy: saved.data.sortBy ?? [],
                    geolocation: state.geolocation,
                });
            },
            setSearchId: searchId => update({searchId}),
            reset: () => {
                inputQuery.current = '';
                commit({query: '', conditions: [], sortBy: []});
            },
            reload: () => setReloadInc(i => i + 1),
        };
    }, [state, reloadInc, setQuery, update, commit, selectFilter, storeEntity]);

    return (
        <SearchContext.Provider value={value}>
            {children}
        </SearchContext.Provider>
    );
}

export function useSearch(): SearchContextValue {
    const ctx = useContext(SearchContext);
    if (!ctx) {
        throw new Error('useSearch must be used within SearchProvider');
    }

    return ctx;
}

export function useOptionalSearch(): SearchContextValue | null {
    return useContext(SearchContext);
}

export {resolveSortBy};

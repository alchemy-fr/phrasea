'use client';

import {
    createContext,
    PropsWithChildren,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type {Asset, ESDebug, Facets} from '@/types/api';
import {searchAssets, SearchAssetsOptions} from '@/lib/api/assets';
import {isAbortError} from '@/lib/api/http';
import {useSearch} from './SearchProvider';
import {resolveSortBy} from './searchState';
import {useAssetStore} from '@/features/assets/assetStore';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';

export type ResultContextValue = {
    pages: Asset[][];
    loading: boolean;
    loadingMore: boolean;
    total?: number;
    facets?: Facets;
    debug?: ESDebug;
    error?: string;
    hasMore: boolean;
    loadMore: () => Promise<void>;
    reload: () => Promise<void>;
    /** increments at every new search (used to reset selection / scroll) */
    searchGeneration: number;
};

const ResultContext = createContext<ResultContextValue | null>(null);
export const ResultContextInternal = ResultContext;

export function ResultProvider({
    children,
    savedSearch,
    extraOptions,
}: PropsWithChildren<{
    savedSearch?: string;
    extraOptions?: Partial<SearchAssetsOptions>;
}>) {
    const search = useSearch();
    const [state, setState] = useState<{
        pages: Asset[][];
        loading: boolean;
        loadingMore: boolean;
        total?: number;
        next?: string;
        facets?: Facets;
        debug?: ESDebug;
        error?: string;
        generation: number;
    }>({pages: [], loading: true, loadingMore: false, generation: 0});
    const controller = useRef<AbortController | null>(null);
    const storeAssets = useAssetStore(s => s.setAssets);
    const reloadAsset = useAssetStore(s => s.reloadAsset);

    useChannelEvent(
        'assets',
        'rendition-update',
        (event: {assetId: string}) => {
            void reloadAsset(event.assetId);
        }
    );

    const extraOptionsKey = JSON.stringify(extraOptions ?? null);
    const stableExtraOptions = useMemo(
        () =>
            JSON.parse(extraOptionsKey) as Partial<SearchAssetsOptions> | null,
        [extraOptionsKey]
    );

    const run = useCallback(
        async (nextUrl?: string) => {
            controller.current?.abort();
            const ctrl = new AbortController();
            controller.current = ctrl;

            setState(prev => ({
                ...prev,
                loading: !nextUrl,
                loadingMore: !!nextUrl,
                error: undefined,
            }));

            const order: Record<string, 'asc' | 'desc'> = {};
            const sortBy = resolveSortBy(search.sortBy);
            sortBy.forEach(s => {
                order[s.a] = s.w === 1 ? 'desc' : 'asc';
            });
            const group = sortBy.filter(s => s.g).map(s => s.a);

            try {
                const result = await searchAssets(
                    {
                        url: nextUrl,
                        query: search.query,
                        conditions: search.conditions
                            .filter(c => !c.disabled)
                            .map(c => c.query),
                        order,
                        group: group.length > 0 ? group.slice(0, 1) : undefined,
                        savedSearch,
                        context: search.geolocation
                            ? {position: search.geolocation}
                            : undefined,
                        ...stableExtraOptions,
                    },
                    ctrl.signal
                );
                if (ctrl.signal.aborted) {
                    return;
                }
                storeAssets(result.items);
                setState(prev => ({
                    pages: nextUrl
                        ? [...prev.pages, result.items]
                        : [result.items],
                    next: result.next,
                    total: result.total,
                    facets: result.facets,
                    debug: result.debug,
                    loading: false,
                    loadingMore: false,
                    generation: nextUrl ? prev.generation : prev.generation + 1,
                }));
            } catch (e: any) {
                if (isAbortError(e) || ctrl.signal.aborted) {
                    return;
                }
                setState(prev => ({
                    ...prev,
                    loading: false,
                    loadingMore: false,
                    error: e?.message ?? String(e),
                }));
            }
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [
            search.checksum,
            search.geolocation,
            savedSearch,
            storeAssets,
            stableExtraOptions,
        ]
    );

    useEffect(() => {
        void run();

        return () => controller.current?.abort();
    }, [run, search.reloadInc]);

    const value = useMemo<ResultContextValue>(
        () => ({
            pages: state.pages,
            loading: state.loading,
            loadingMore: state.loadingMore,
            total: state.total,
            facets: state.facets,
            debug: state.debug,
            error: state.error,
            hasMore: !!state.next,
            loadMore: async () => {
                if (state.next && !state.loadingMore) {
                    await run(state.next);
                }
            },
            reload: () => run(),
            searchGeneration: state.generation,
        }),
        [state, run]
    );

    return (
        <ResultContext.Provider value={value}>
            {children}
        </ResultContext.Provider>
    );
}

export function useResults(): ResultContextValue {
    const ctx = useContext(ResultContext);
    if (!ctx) {
        throw new Error('useResults must be used within ResultProvider');
    }

    return ctx;
}

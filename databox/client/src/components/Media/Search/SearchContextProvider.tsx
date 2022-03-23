import {SearchContext} from "./SearchContext";
import {PropsWithChildren, useCallback, useContext, useEffect, useState} from "react";
import {ESDebug, getAssets} from "../../../api/asset";
import {Asset} from "../../../types";
import {SearchFiltersContext} from "./SearchFiltersContext";
import {TFacets} from "../Asset/Facets";
import {Filters} from "./Filter";
import axios from "axios";
import useHash from "../../../lib/useHash";
import {hashToQuery, queryToHash} from "./search";

let lastController: AbortController;

async function search(query: string, url?: string, collectionIds?: string[], workspaceIds?: string[], attrFilters?: Filters): Promise<{
    result: Asset[];
    facets: TFacets;
    total: number;
    next: string | null;
    debug: ESDebug;
}> {

    if (lastController) {
        lastController.abort();
    }

    lastController = new AbortController();

    const options = {
        query,
        parents: collectionIds,
        workspaces: workspaceIds,
        url,
        filters: attrFilters?.map(f => ({
            ...f,
            t: undefined,
        })),
    };

    const result = await getAssets(options, {
        signal: lastController.signal,
    });

    return {
        total: result.total,
        facets: result.facets!,
        result: result.result,
        next: result.next,
        debug: result.debug,
    };
}

function extractCollectionIdFromPath(path: string): string {
    const p = path.split('/');
    return p[p.length - 1];
}

type State = {
    pages: Asset[][];
    loading: boolean;
    facets?: TFacets;
    total?: number;
    next?: string | null;
    loadNext?: string;
    inc: number;
    debug?: ESDebug;
};

type Props = PropsWithChildren<{}>;

export default function SearchContextProvider({children}: Props) {
    const searchFiltersContext = useContext(SearchFiltersContext);

    const [hash, setHash] = useHash();

    const [state, setState] = useState<State>({
        pages: [],
        loading: false,
        inc: 0,
    });

    const {query, filters} = hashToQuery(hash);

    const setAttrFilters = useCallback((handler: (prev: Filters) => Filters): void => {
        setHash(queryToHash(query, handler(filters)));
    }, [setHash, query, filters]);

    const setQuery = useCallback((handler: string | ((prev: string) => string)): void => {
        if (typeof handler === 'string') {
            setHash(queryToHash(handler, filters));
            return;
        }

        setHash(queryToHash(handler(query), filters));
    }, [setHash, query, filters]);

    const setLoading = (loading: boolean) => setState((prev) => ({
        ...prev,
        loading,
    }));

    const doSearch = async (nextUrl?: string) => {
        setLoading(true);

        const collectionIds = searchFiltersContext.selectedCollection ? [extractCollectionIdFromPath(searchFiltersContext.selectedCollection)] : undefined;
        const workspaceIds = searchFiltersContext.selectedWorkspace ? [searchFiltersContext.selectedWorkspace] : undefined;

        search(query, nextUrl, collectionIds, workspaceIds, filters).then((r) => {
            setState((prevState) => {
                return {
                    pages: nextUrl ? prevState.pages.concat([r.result]) : [r.result],
                    next: r.next,
                    total: r.total,
                    loading: false,
                    facets: r.facets,
                    inc: 0,
                    debug: r.debug,
                }
            });
        }).catch((e) => {
            if (e instanceof axios.Cancel) {
            } else {
                console.log('e', e);
                setLoading(false);
            }
        })
    }

    const reload = () => {
        doSearch();
    };

    const toggleAttrFilter = (attrName: string, value: string, attrTitle: string): void => {
        setAttrFilters(prev => {
            const f = [...prev];

            const key = f.findIndex(_f => _f.a === attrName && !_f.i);

            if (key >= 0) {
                const tf = f[key];
                if (tf.v.includes(value)) {
                    if (tf.v.length === 1) {
                        f.splice(key, 1);
                    } else {
                        tf.v = tf.v.filter(v => v !== value);
                    }
                } else {
                    tf.v = tf.v.concat(value);
                }
            } else {
                f.push({
                    t: attrTitle,
                    a: attrName,
                    v: [value],
                });
            }

            return f;
        });
    };

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

    useEffect(() => {
        doSearch();
        // eslint-disable-next-line
    }, [
        searchFiltersContext.selectedCollection,
        searchFiltersContext.selectedWorkspace,
        searchFiltersContext.reloadInc,
        hash,
    ]);

    return <SearchContext.Provider
        value={{
            query,
            setQuery,
            reload,
            toggleAttrFilter,
            removeAttrFilter,
            invertAttrFilter,
            attrFilters: filters,
            loading: state.loading,
            pages: state.pages,
            facets: state.facets,
            total: state.total,
            debug: state.debug,
            loadMore: state.next ? async () => {
                await doSearch(state.next!);
            } : undefined,
        }}
    >
        {children}
    </SearchContext.Provider>
}

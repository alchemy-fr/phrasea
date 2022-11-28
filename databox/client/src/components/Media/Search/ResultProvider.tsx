import {ResultContext} from "./ResultContext";
import {PropsWithChildren, useContext, useEffect, useState} from "react";
import {ESDebug, getAssets} from "../../../api/asset";
import {Asset} from "../../../types";
import {SearchContext} from "./SearchContext";
import {extractLabelValueFromKey, TFacets} from "../Asset/Facets";
import {Filters, SortBy} from "./Filter";
import axios from "axios";

let lastController: AbortController;

async function search(query: string, sortBy: SortBy[], url?: string, collectionIds?: string[], workspaceIds?: string[], attrFilters?: Filters): Promise<{
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

    const order: Record<string, 'asc' | 'desc'> = {};
    sortBy.forEach(s => {
        order[s.a] = s.w === 1 ? 'desc' : 'asc';
    });

    const groupBy = sortBy.map((s) => {
        if (s.g) {
            return s.a;
        }
    });

    const options = {
        query,
        parents: collectionIds,
        workspaces: workspaceIds,
        url,
        filters: attrFilters?.map((f) => ({
            ...f,
            v: f.v.map(v => extractLabelValueFromKey(v).value),
            t: undefined,
        })),
        group: groupBy.length > 0 ? groupBy.slice(0, 1) : undefined,
        order,
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
    debug?: ESDebug;
};

type Props = PropsWithChildren<{}>;

export default function ResultProvider({children}: Props) {
    const searchContext = useContext(SearchContext);

    const [state, setState] = useState<State>({
        pages: [],
        loading: false,
    });

    const setLoading = (loading: boolean) => setState((prev) => ({
        ...prev,
        loading,
    }));

    const doSearch = async (nextUrl?: string) => {
        setLoading(true);

        const collectionIds = searchContext.collectionId ? [extractCollectionIdFromPath(searchContext.collectionId)] : undefined;
        const workspaceIds = searchContext.workspaceId ? [searchContext.workspaceId] : undefined;

        search(
            searchContext.query,
            searchContext.sortBy,
            nextUrl,
            collectionIds,
            workspaceIds,
            searchContext.attrFilters
        ).then((r) => {
            setState((prevState) => {
                return {
                    pages: nextUrl ? prevState.pages.concat([r.result]) : [r.result],
                    next: r.next,
                    total: r.total,
                    loading: false,
                    facets: r.facets,
                    debug: r.debug,
                }
            });
        }).catch((e) => {
            if (e instanceof axios.Cancel) {
            } else {
                console.error(e);
                setLoading(false);
            }
        })
    }

    useEffect(() => {
        doSearch();
        // eslint-disable-next-line
    }, [
        searchContext.searchChecksum,
        searchContext.reloadInc,
    ]);

    return <ResultContext.Provider
        value={{
            loading: state.loading,
            pages: state.pages,
            facets: state.facets,
            total: state.total,
            debug: state.debug,
            loadMore: state.next ? async () => {
                await doSearch(state.next!);
            } : undefined,
            reload: doSearch,
        }}
    >
        {children}
    </ResultContext.Provider>
}

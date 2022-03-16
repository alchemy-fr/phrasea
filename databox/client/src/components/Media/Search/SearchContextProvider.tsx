import {SearchContext} from "./SearchContext";
import {PropsWithChildren, useContext, useEffect, useState} from "react";
import {getAssets} from "../../../api/asset";
import {Asset} from "../../../types";
import {SearchFiltersContext} from "./SearchFiltersContext";
import {TFacets} from "../Asset/Facets";

async function search(query: string, url?: string, collectionIds?: string[], workspaceIds?: string[], attrFilters?: AttrFilters): Promise<{
    result: Asset[];
    facets: TFacets;
    total: number;
    next: string | null;
}> {
    const options = {
        query,
        parents: collectionIds,
        workspaces: workspaceIds,
        url,
        filters: attrFilters,
    };

    const result = await getAssets(options);

    return {
        total: result.total,
        facets: result.facets!,
        result: result.result,
        next: result.next,
    };
}

function extractCollectionIdFromPath(path: string): string {
    const p = path.split('/');
    return p[p.length - 1];
}

export type AttrFilters = Record<string, string[]>;

type State = {
    pages: Asset[][];
    loading: boolean;
    facets?: TFacets;
    total?: number;
    next?: string | null;
    loadNext?: string;
    inc: number;
};

type Props = PropsWithChildren<{}>;

export default function SearchContextProvider({children}: Props) {
    const searchFiltersContext = useContext(SearchFiltersContext);

    const [query, setQuery] = useState('');
    const [attrFilters, setAttrFilters] = useState<AttrFilters>({});
    const [state, setState] = useState<State>({
        pages: [],
        loading: false,
        inc: 0,
    });

    const setLoading = (loading: boolean) => setState((prev) => ({
        ...prev,
        loading,
    }));

    const doSearch = async (nextUrl?: string) => {
        if (state.loading) {
            return;
        }
        setLoading(true);

        const collectionIds = searchFiltersContext.selectedCollection ? [extractCollectionIdFromPath(searchFiltersContext.selectedCollection)] : undefined;
        const workspaceIds = searchFiltersContext.selectedWorkspace ? [searchFiltersContext.selectedWorkspace] : undefined;

        search(query, nextUrl, collectionIds, workspaceIds, attrFilters).then((r) => {
            setState((prevState) => {
                return {
                    pages: nextUrl ? prevState.pages.concat([r.result]) : [r.result],
                    next: r.next,
                    total: r.total,
                    loading: false,
                    facets: r.facets,
                    inc: 0,
                }
            });
        }).catch((e) => {
            setLoading(false);
        })
    }

    const reload = () => {
        doSearch();
    };

    const toggleAttrFilter = (attrName: string, value: string) => {
        setAttrFilters(prev => ({
            ...prev,
            [attrName]: prev[attrName] ? (
                !prev[attrName].includes(value) ? prev[attrName].concat(value) : prev[attrName].filter(v => v !== value)
            ) : [value],
        }));
    };

    const removeAttrFilter = (attrName: string) => {
        setAttrFilters(prev => {
            const f = {...prev};
            delete f[attrName];

            return f;
        });
    };

    useEffect(() => {
        doSearch();
        // eslint-disable-next-line
    }, [
        query,
        searchFiltersContext.selectedCollection,
        searchFiltersContext.selectedWorkspace,
        searchFiltersContext.reloadInc,
        attrFilters,
    ]);

    return <SearchContext.Provider
        value={{
            query,
            setQuery,
            reload,
            toggleAttrFilter: toggleAttrFilter,
            removeAttrFilter: removeAttrFilter,
            attrFilters: attrFilters,
            loading: state.loading,
            pages: state.pages,
            facets: state.facets,
            total: state.total,
            loadMore: state.next ? async () => {
                await doSearch(state.next!);
            } : undefined,
        }}
    >
        {children}
    </SearchContext.Provider>
}

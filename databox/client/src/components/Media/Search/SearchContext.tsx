import React from "react";
import {Asset} from "../../../types";
import {TFacets} from "../Asset/Facets";

export type TSearchContext = {
    query: string;
    setQuery: (query: string) => void;
    reload: () => void;
    loadMore?: () => Promise<void> | undefined;
    loading: boolean;
    collections?: string[];
    workspaces?: string[];
    pages: Asset[][];
    total?: number;
    facets?: TFacets;
    attrFilters: Record<string, string[]>;
    toggleAttrFilter: (attrName: string, value: string) => void;
}

export const SearchContext = React.createContext<TSearchContext>({
    query: '',
    pages: [],
    attrFilters: {},
    loading: false,
    setQuery: () => {},
    reload: () => {},
    toggleAttrFilter: () => {},
});

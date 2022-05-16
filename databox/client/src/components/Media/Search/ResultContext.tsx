import React from "react";
import {Asset} from "../../../types";
import {BucketKeyValue, TFacets} from "../Asset/Facets";
import {Filters} from "./Filter";
import {ESDebug} from "../../../api/asset";

export type TResultContext = {
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
    debug?: ESDebug;
    attrFilters: Filters;
    toggleAttrFilter: (attrName: string, value: BucketKeyValue, attrTitle: string) => void;
    removeAttrFilter: (key: number) => void;
    invertAttrFilter: (key: number) => void;
}

export const ResultContext = React.createContext<TResultContext>({
    query: '',
    pages: [],
    attrFilters: [],
    loading: false,
    setQuery: () => {},
    reload: () => {},
    toggleAttrFilter: () => {},
    removeAttrFilter: () => {},
    invertAttrFilter: () => {},
});

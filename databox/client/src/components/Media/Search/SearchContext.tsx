import React from "react";
import {BucketKeyValue, FacetType} from "../Asset/Facets";
import {Filters, OrderBy} from "./Filter";

export type TSearchContext = {
    collectionId?: string;
    workspaceId?: string;
    selectCollection: (absolutePath: string | undefined, forceReload?: boolean) => void;
    selectWorkspace: (id: string | undefined, forceReload?: boolean) => void;
    collections?: string[];
    workspaces?: string[];
    query: string;
    setQuery: (query: string, force?: boolean) => void;
    setAttrFilter: (attrName: string, values: BucketKeyValue[], attrTitle: string, type?: FacetType) => void;
    toggleAttrFilter: (attrName: string, value: BucketKeyValue, attrTitle: string) => void;
    removeAttrFilter: (key: number) => void;
    invertAttrFilter: (key: number) => void;
    attrFilters: Filters;
    orderBy: OrderBy[];
    setOrderBy: (handler: (prev: OrderBy[]) => OrderBy[]) => void;
    searchChecksum?: string;
    reloadInc: number;
}

export const SearchContext = React.createContext<TSearchContext>({
    query: '',
    attrFilters: [],
    orderBy: [],
    selectCollection: () => {
    },
    selectWorkspace: () => {
    },
    setQuery: () => {
    },
    setAttrFilter: () => {
    },
    toggleAttrFilter: () => {
    },
    removeAttrFilter: () => {
    },
    invertAttrFilter: () => {
    },
    setOrderBy: () => {
    },
    reloadInc: 0,
});

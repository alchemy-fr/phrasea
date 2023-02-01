import React from "react";
import {ResolvedBucketValue, FacetType} from "../Asset/Facets";
import {Filters, FilterType, SortBy} from "./Filter";

export type TSearchContext = {
    collectionId?: string;
    workspaceId?: string;
    selectCollection: (absolutePath: string | undefined, forceReload?: boolean) => void;
    selectWorkspace: (id: string | undefined, forceReload?: boolean) => void;
    collections?: string[];
    workspaces?: string[];
    query: string;
    setQuery: (query: string, force?: boolean) => void;
    geolocation?: string | undefined;
    setGeoLocation: (position: string | undefined) => void;
    setAttrFilter: (attrName: string, type: FilterType | undefined, values: ResolvedBucketValue[], attrTitle: string, widget?: FacetType | undefined) => void;
    toggleAttrFilter: (attrName: string, type: FilterType | undefined, value: ResolvedBucketValue, attrTitle: string) => void;
    removeAttrFilter: (key: number) => void;
    invertAttrFilter: (key: number) => void;
    attrFilters: Filters;
    sortBy: SortBy[];
    setSortBy: (newSortBy: SortBy[]) => void;
    searchChecksum?: string;
    reloadInc: number;
}

export const SearchContext = React.createContext<TSearchContext>({
    query: '',
    attrFilters: [],
    sortBy: [],
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
    setSortBy: () => {
    },
    setGeoLocation: () => {
    },
    reloadInc: 0,
});

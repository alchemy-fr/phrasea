import {FacetType, ResolvedBucketValue} from '../Asset/Facets';
import {Filters, FilterType, SortBy} from './Filter';
import React from 'react';

export type TSearchContext = {
    workspaceId?: string;
    selectCollection: (
        absolutePath: string | undefined,
        title: string | undefined,
        forceReload?: boolean
    ) => void;
    selectWorkspace: (
        id: string | undefined,
        title: string | undefined,
        options?: {
            forceReload?: boolean;
            clearSearch?: boolean;
        }
    ) => void;
    collections: string[];
    workspaces: string[];
    query: string;
    setQuery: (query: string, force?: boolean) => void;
    geolocation?: string | undefined;
    setGeoLocation: (position: string | undefined) => void;
    setAttrFilter: (
        attrName: string,
        type: FilterType | undefined,
        values: ResolvedBucketValue[],
        attrTitle: string,
        widget?: FacetType | undefined
    ) => void;
    toggleAttrFilter: (
        attrName: string,
        type: FilterType | undefined,
        value: ResolvedBucketValue,
        attrTitle: string
    ) => void;
    removeAttrFilter: (key: number) => void;
    invertAttrFilter: (key: number) => void;
    reset: () => void;
    attrFilters: Filters;
    sortBy: SortBy[];
    setSortBy: (newSortBy: SortBy[]) => void;
    searchChecksum?: string;
    reloadInc: number;
};

export const SearchContext = React.createContext<TSearchContext>({
    query: '',
    attrFilters: [],
    sortBy: [],
    collections: [],
    workspaces: [],
    selectCollection: () => {},
    selectWorkspace: () => {},
    setQuery: () => {},
    setAttrFilter: () => {},
    toggleAttrFilter: () => {},
    removeAttrFilter: () => {},
    invertAttrFilter: () => {},
    setSortBy: () => {},
    reset: () => {},
    setGeoLocation: () => {},
    reloadInc: 0,
});

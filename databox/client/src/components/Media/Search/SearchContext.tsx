import {SortBy} from './Filter';
import React, {RefObject} from 'react';
import {AQLQueries, AQLQuery} from './AQL/query.ts';

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
        forceReload?: boolean
    ) => void;
    collections: string[];
    workspaces: string[];
    query: string;
    setQuery: (query: string, force?: boolean) => void;
    inputQuery: RefObject<string>;
    setInputQuery: (query: string) => void;
    geolocation?: string | undefined;
    setGeoLocation: (position: string | undefined) => void;
    reset: () => void;
    conditions: AQLQueries;
    upsertCondition: (query: AQLQuery) => void;
    removeCondition: (query: AQLQuery) => void;
    sortBy: SortBy[];
    setSortBy: (newSortBy: SortBy[]) => void;
    searchChecksum?: string;
    reloadInc: number;
};

export const SearchContext = React.createContext<TSearchContext | undefined>(
    undefined
);

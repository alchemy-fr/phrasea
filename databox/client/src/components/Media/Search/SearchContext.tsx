import React from "react";
import {BucketKeyValue} from "../Asset/Facets";
import {Filters} from "./Filter";

export type TSearchContext = {
    collectionId?: string;
    workspaceId?: string;
    selectCollection: (absolutePath: string | undefined, forceReload?: boolean) => void;
    selectWorkspace: (id: string | undefined, forceReload?: boolean) => void;
    collections?: string[];
    workspaces?: string[];
    query: string;
    setQuery: (query: string, force?: boolean) => void;
    toggleAttrFilter: (attrName: string, value: BucketKeyValue, attrTitle: string) => void;
    removeAttrFilter: (key: number) => void;
    invertAttrFilter: (key: number) => void;
    attrFilters: Filters;
    searchChecksum?: string;
    reloadInc: number;
}

export const SearchContext = React.createContext<TSearchContext>({
    query: '',
    attrFilters: [],
    selectCollection: () => {},
    selectWorkspace: () => {},
    setQuery: () => {},
    toggleAttrFilter: () => {},
    removeAttrFilter: () => {},
    invertAttrFilter: () => {},
    reloadInc: 0,
});

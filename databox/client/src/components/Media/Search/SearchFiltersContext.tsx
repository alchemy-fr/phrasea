import React from "react";

export type TSearchFiltersContext = {
    selectedCollection?: string;
    selectedWorkspace?: string;
    selectCollection: (absolutePath: string | undefined, forceReload?: boolean) => void;
    selectWorkspace: (id: string | undefined, forceReload?: boolean) => void;
    reloadInc: number;
}

export const SearchFiltersContext = React.createContext<TSearchFiltersContext>({
    reloadInc: 0,
    selectCollection: () => {},
    selectWorkspace: () => {},
});

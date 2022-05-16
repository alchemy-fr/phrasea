import React from "react";

export type TSearchContext = {
    selectedCollection?: string;
    selectedWorkspace?: string;
    selectCollection: (absolutePath: string | undefined, forceReload?: boolean) => void;
    selectWorkspace: (id: string | undefined, forceReload?: boolean) => void;
    reloadInc: number;
}

export const SearchContext = React.createContext<TSearchContext>({
    reloadInc: 0,
    selectCollection: () => {},
    selectWorkspace: () => {},
});

import React from "react";

export type TSelectionContext = {
    selectedCollection?: string;
    selectedWorkspace?: string;
    reloadInc: number;
    selectedAssets: string[];
    selectCollection: (absolutePath: string, forceReload?: boolean) => void;
    selectWorkspace: (id: string, forceReload?: boolean) => void;
    selectAssets: (ids: string[]) => void;
    resetAssetSelection: () => void;
}

export const SelectionContext = React.createContext<TSelectionContext>({
    selectedAssets: [],
    reloadInc: 0,
    selectCollection: () => {},
    selectWorkspace: () => {},
    selectAssets: (ids: string[]) => {},
    resetAssetSelection: () => {},
});

import React from "react";

export type TSelectionContext = {
    selectedCollection?: string;
    selectedWorkspace?: string;
    selectedAssets: string[];
    selectCollection: (absolutePath: string) => void;
    selectWorkspace: (id: string) => void;
    selectAssets: (ids: string[]) => void;
}

export const SelectionContext = React.createContext<TSelectionContext>({
    selectedAssets: [],
    selectCollection: () => {},
    selectWorkspace: () => {},
    selectAssets: (ids: string[]) => {},
});

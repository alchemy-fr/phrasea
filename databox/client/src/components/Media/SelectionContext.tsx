import React from "react";

export type TSelectionContext = {
    selectedCollection?: string;
    selectedAssets: string[];
    selectCollection: (absolutePath: string) => void;
    selectAssets: (ids: string[]) => void;
}

export const SelectionContext = React.createContext<TSelectionContext>({
    selectedAssets: [],
    selectCollection: () => {},
    selectAssets: (ids: string[]) => {},
});

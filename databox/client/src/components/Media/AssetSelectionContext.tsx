import React from "react";

export type TAssetSelectionContext = {
    selectedAssets: string[];
    selectAssets: (ids: string[]) => void;
}

export const AssetSelectionContext = React.createContext<TAssetSelectionContext>({
    selectedAssets: [],
    selectAssets: () => {},
});

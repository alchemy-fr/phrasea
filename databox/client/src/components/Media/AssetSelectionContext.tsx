import React from 'react';

export type TAssetSelectionContext = {
    selectedAssets: string[];
    selectAssets: (ids: string[] | ((prev: string[]) => string[])) => void;
};

export const AssetSelectionContext =
    React.createContext<TAssetSelectionContext>({
        selectedAssets: [],
        selectAssets: () => {},
    });

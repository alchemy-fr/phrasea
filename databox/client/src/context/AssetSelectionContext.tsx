import React from 'react';
import {Asset, AssetOrAssetContainer, StateSetter} from "../types.ts";
import {ItemToAssetFunc} from "../components/AssetList/types.ts";

export type TSelectionContext<T extends AssetOrAssetContainer> = {
    selection: T[];
    setSelection: StateSetter<T[]>;
    itemToAsset?: ItemToAssetFunc<T> | undefined;
};

export const AssetSelectionContext =
    React.createContext<TSelectionContext<Asset>>({
        selection: [],
        setSelection: () => {},
    });

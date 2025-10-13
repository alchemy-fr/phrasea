import React from 'react';
import {Asset, AssetOrAssetContainer, StateSetter} from '../types';
import {ItemToAssetFunc} from '../components/AssetList/types';

export type TSelectionContext<T extends AssetOrAssetContainer> = {
    selection: T[];
    disabledAssets: T[];
    setSelection: StateSetter<T[]>;
    itemToAsset?: ItemToAssetFunc<T> | undefined;
};

export const AssetSelectionContext = React.createContext<
    TSelectionContext<Asset>
>({
    selection: [],
    disabledAssets: [],
    setSelection: () => {},
});

import React from 'react';
import {BasketAsset} from '../types';
import {TSelectionContext} from './AssetSelectionContext';

export const BasketSelectionContext = React.createContext<
    TSelectionContext<BasketAsset>
>({
    selection: [],
    setSelection: () => {},
    itemToAsset: item => item.asset,
    disabledAssets: [],
});

import React from 'react';
import {BasketAsset} from '../types.ts';
import {TSelectionContext} from './AssetSelectionContext.tsx';

export const BasketSelectionContext = React.createContext<
    TSelectionContext<BasketAsset>
>({
    selection: [],
    setSelection: () => {},
    itemToAsset: item => item.asset,
});

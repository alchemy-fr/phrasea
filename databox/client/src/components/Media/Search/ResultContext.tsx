import {Asset} from '../../../types';
import {TFacets} from '../Asset/Facets';
import {ESDebug} from '../../../api/asset';
import React from 'react';
import {ReloadFunc} from '../../AssetList/types';

export type TResultContext = {
    loading: boolean;
    pages: Asset[][];
    total?: number;
    facets?: TFacets;
    debug?: ESDebug;
    loadMore?: (() => Promise<void>) | undefined;
    reload: ReloadFunc;
};

export const ResultContext = React.createContext<TResultContext>({
    pages: [],
    loading: false,
    reload: async () => {},
});

import {create} from 'zustand';
import {Asset} from '../types';

type State = {
    assets: Record<string, Asset>;
    update: (asset: Asset) => void;
    delete: (id: string) => void;
    setAssets: (assets: Asset[]) => void;
};

export const useAssetStore = create<State>(set => ({
    assets: {},
    delete(id: string): void {
        set(state => {
            const assets = {...state.assets};
            delete assets[id];

            return {assets};
        });
    },
    update(asset: Asset): void {
        set(state => {
            const assets = {...state.assets};
            assets[asset.id] = asset;

            return {assets};
        });
    },
    setAssets(assets: Asset[]): void {
        set(state => {
            const list = {...state.assets};
            assets.forEach(a => {
                list[a.id] = a;
            });

            return {assets: list};
        });
    },
}));

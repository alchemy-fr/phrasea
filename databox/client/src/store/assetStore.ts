import {create} from 'zustand';
import {Asset} from '../types';
import {getAsset} from '../api/asset.ts';

type State = {
    assets: Record<string, Asset>;
    update: (asset: Asset) => void;
    delete: (id: string) => void;
    loadAsset: (id: string) => Promise<void>;
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
    loadAsset: async (id: string) => {
        const asset = await getAsset(id);
        set(state => {
            const assets = {...state.assets};
            assets[asset.id] = asset;

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

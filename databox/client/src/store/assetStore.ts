import {create} from 'zustand';
import {Asset} from '../types';

type State = {
    assets: Record<string, Asset>;
    update: (asset: Asset) => void;
    delete: (id: string) => void;
};

export const useAssetStore = create<State>((set, getState) => ({
    assets: {},
    delete(id: string): void {
        const assets = getState().assets;
        delete assets[id];
        set({assets});
    },
    update(asset: Asset): void {
        set(state => {
            const assets = {...state.assets};
            assets[asset.id] = asset;
            return {assets};
        });
    },
}));

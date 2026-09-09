import {create} from 'zustand';
import type {Asset} from '@/types/api';
import {getAsset} from '@/lib/api/assets';

type State = {
    assets: Record<string, Asset>;
    setAssets: (assets: Asset[]) => void;
    update: (asset: Asset) => void;
    remove: (ids: string[]) => void;
    reloadAsset: (id: string) => Promise<void>;
};

/**
 * Shared cache of assets displayed anywhere in the app so that updates made
 * from a dialog (attribute edit, rendition update...) are reflected in lists.
 */
export const useAssetStore = create<State>((set, get) => ({
    assets: {},
    setAssets: assets =>
        set(s => {
            const next = {...s.assets};
            assets.forEach(a => {
                next[a.id] = a;
            });

            return {assets: next};
        }),
    update: asset => set(s => ({assets: {...s.assets, [asset.id]: asset}})),
    remove: ids =>
        set(s => {
            const next = {...s.assets};
            ids.forEach(id => delete next[id]);

            return {assets: next};
        }),
    reloadAsset: async id => {
        if (!get().assets[id]) {
            return;
        }
        try {
            const asset = await getAsset(id);
            get().update(asset);
        } catch {
            // asset may have been deleted
        }
    },
}));

export function useLiveAsset(asset: Asset): Asset {
    return useAssetStore(s => s.assets[asset.id]) ?? asset;
}

import {create} from 'zustand';
import {AssetExport} from '../types.ts';
import {registerWs} from '../lib/pusher.ts';
import {downloadUrl} from '@alchemy/core';

type State = {
    data: AssetExport[];
    addExport: (upload: AssetExport) => void;
};

export const useAssetExportStore = create<State>(set => ({
    data: [],

    addExport: assetExport => {
        set(state => ({
            data: [...state.data, assetExport],
        }));

        const id = assetExport.id;

        registerWs(`export-${id}`, 'progress', (eventData: any) => {
            set(state => ({
                data: state.data.map(d => {
                    if (d.id === id) {
                        return {
                            ...d,
                            progress: eventData.progress,
                        };
                    }
                    return d;
                }),
            }));
        });

        registerWs(`export-${assetExport.id}`, 'ready', (eventData: any) => {
            downloadUrl(eventData.downloadUrl);
        });
    },
}));

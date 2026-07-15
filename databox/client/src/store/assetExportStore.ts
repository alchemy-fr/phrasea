import {create} from 'zustand';
import {AssetExport, ExportStatusEnum} from '../types.ts';
import {registerWs} from '../lib/pusher.ts';
import {downloadUrl} from '@alchemy/core';

type State = {
    data: AssetExport[];
    addExport: (exp: AssetExport) => void;
    removeExport: (exportId: string) => void;
};

export const useAssetExportStore = create<State>(set => ({
    data: [],

    removeExport: (exportId: string): void => {
        set(state => ({
            data: state.data.filter(exp => exp.id !== exportId),
        }));
    },

    addExport: exp => {
        set(state => ({
            data: state.data.concat([exp]),
        }));

        const id = exp.id;
        const channel = `export-${id}`;

        registerWs(channel, 'progress', (eventData: any) => {
            set(state => ({
                data: state.data.map(d => {
                    if (d.id === id) {
                        return {
                            ...d,
                            progress: eventData.progress,
                            status: ExportStatusEnum.Pending,
                        };
                    }
                    return d;
                }),
            }));
        });

        registerWs(channel, 'error', (eventData: any) => {
            set(state => ({
                data: state.data.map(d => {
                    if (d.id === id) {
                        return {
                            ...d,
                            progress: eventData.progress,
                            status: ExportStatusEnum.Failed,
                        };
                    }
                    return d;
                }),
            }));
        });

        registerWs(channel, 'ready', (eventData: any) => {
            set(state => ({
                data: state.data.map(d => {
                    if (d.id === id) {
                        return {
                            ...d,
                            progress: 1,
                            status: ExportStatusEnum.Ready,
                            downloadUrl: eventData.downloadUrl,
                        };
                    }
                    return d;
                }),
            }));
            downloadUrl(eventData.downloadUrl);
        });
    },
}));

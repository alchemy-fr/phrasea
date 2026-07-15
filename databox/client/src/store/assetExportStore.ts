import {create} from 'zustand';
import {AssetExport, ExportStatusEnum} from '../types.ts';
import {registerWs} from '../lib/pusher.ts';
import {downloadUrl, UnregisterWebSocket} from '@alchemy/core';

type Listeners = {
    progress?: UnregisterWebSocket;
    error?: UnregisterWebSocket;
    ready?: UnregisterWebSocket;
};

type State = {
    data: AssetExport[];
    addExport: (exp: AssetExport) => void;
    removeExport: (exportId: string) => void;
};

export const useAssetExportStore = create<State>(set => ({
    data: [],
    listeners: {},

    removeExport: (exportId: string): void => {
        set(state => ({
            data: state.data.filter(exp => {
                if (exp.id === exportId) {
                    exp.unregister?.();
                }

                return exp.id !== exportId;
            }),
        }));
    },

    addExport: exp => {
        const id = exp.id;
        const channel = `export-${id}`;

        const listeners: Listeners = {};
        const unregister = () => {
            listeners.error?.();
            listeners.progress?.();
            listeners.ready?.();
        };

        listeners.progress = registerWs(
            channel,
            'progress',
            (eventData: any) => {
                set(state => ({
                    data: state.data.map(d => {
                        if (d.id === id) {
                            return {
                                ...d,
                                progress: eventData.progress,
                                status: ExportStatusEnum.InProgress,
                            };
                        }
                        return d;
                    }),
                }));
            }
        );

        listeners.error = registerWs(channel, 'error', (eventData: any) => {
            unregister();
            set(state => ({
                data: state.data.map(d => {
                    if (d.id === id) {
                        return {
                            ...d,
                            progress: eventData.progress,
                            status: ExportStatusEnum.Failed,
                            error: eventData.error,
                        };
                    }
                    return d;
                }),
            }));
        });

        listeners.ready = registerWs(channel, 'ready', (eventData: any) => {
            unregister();
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

        exp.unregister = unregister;

        set(state => ({
            data: state.data.concat([exp]),
        }));
    },
}));

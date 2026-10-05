import {create} from 'zustand';
import {AssetExport, ExportStatus} from '@/types/api';

type State = {
    exports: AssetExport[];
    add: (exp: AssetExport) => void;
    update: (id: string, patch: Partial<AssetExport>) => void;
    remove: (id: string) => void;
};

export const useExportStore = create<State>(set => ({
    exports: [],
    add: exp =>
        set(s => ({exports: [...s.exports.filter(e => e.id !== exp.id), exp]})),
    update: (id, patch) =>
        set(s => ({
            exports: s.exports.map(e => (e.id === id ? {...e, ...patch} : e)),
        })),
    remove: id => set(s => ({exports: s.exports.filter(e => e.id !== id)})),
}));

export {ExportStatus};

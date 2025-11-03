import {create} from 'zustand';

export type FileUpload = {
    id: string;
    file: File;
    progress: number;
    assetId?: string;
    renditionId?: string;
    error?: string;
};

type State = {
    uploads: FileUpload[];
    addUpload: (upload: FileUpload) => void;
    uploadProgress: (upload: FileUpload) => void;
    uploadError: (upload: FileUpload) => void;
    removeUpload: (id: string) => void;
};

export const useUploadStore = create<State>(set => ({
    uploads: [],

    addUpload: (upload: FileUpload) => {
        set(state => ({
            uploads: [...state.uploads.filter(u => u.progress < 1), upload],
        }));
    },

    uploadProgress: (upload: FileUpload) =>
        set(state => {
            const uploads = state.uploads.map(u =>
                u.id === upload.id ? {...u, progress: upload.progress} : u
            );
            return {uploads};
        }),

    uploadError: (upload: FileUpload) =>
        set(state => {
            const uploads = state.uploads.map(u =>
                u.id === upload.id ? {...u, error: upload.error} : u
            );
            return {uploads};
        }),

    removeUpload: (id: string) =>
        set(state => ({
            uploads: state.uploads.filter(upload => upload.id !== id),
        })),
}));

import {create} from 'zustand';

export type PendingUpload = {
    id: string;
    file: File;
    progress: number;
    error?: string;
    abort?: () => void;
};

type State = {
    uploads: PendingUpload[];
    add: (upload: PendingUpload) => void;
    setProgress: (id: string, progress: number) => void;
    setError: (id: string, error: string) => void;
    remove: (id: string) => void;
    clearFinished: () => void;
};

export const useUploadStore = create<State>(set => ({
    uploads: [],
    add: upload => set(s => ({uploads: [...s.uploads, upload]})),
    setProgress: (id, progress) =>
        set(s => ({
            uploads: s.uploads.map(u => (u.id === id ? {...u, progress} : u)),
        })),
    setError: (id, error) =>
        set(s => ({
            uploads: s.uploads.map(u =>
                u.id === id ? {...u, error, progress: 1} : u
            ),
        })),
    remove: id => set(s => ({uploads: s.uploads.filter(u => u.id !== id)})),
    clearFinished: () =>
        set(s => ({
            uploads: s.uploads.filter(u => u.progress < 1 && !u.error),
        })),
}));

import {create} from 'zustand';

export type FileUpload = {
    id: string;
    file: File;
    progress: number;
    assetId?: string;
    renditionId?: string;
};

type State = {
    uploads: FileUpload[];
    addUpload: (upload: FileUpload) => void;
    uploadProgress: (upload: FileUpload) => void;
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

    removeUpload: (id: string) =>
        set(state => ({
            uploads: state.uploads.filter(upload => upload.id !== id),
        })),
}));

// setTimeout(() => {
//     const s = useUploadStore.getState();
//     const svg = `
// <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50">
//    <circle cx="25" cy="25" r="20"/>
// </svg>`;
//
//     s.addUpload({
//         id: 'example-upload',
//         file: new File([svg], 'example.svg', {
//             type: 'image/svg+xml'
//         }),
//
//         progress: 0,
//     });
//
//     s.addUpload({
//         id: 'example-upload2',
//         file: new File([''], 'another-file.mp3'),
//         progress: 0,
//     });
//
//     setInterval(() => {
//         const state = useUploadStore.getState();
//
//         state.uploads.forEach(upload => {
//             if (upload.progress < 1) {
//                 const newProgress = Math.min(upload.progress + Math.random() * 0.1, 1);
//                 state.uploadProgress({...upload, progress: newProgress});
//             }
//         });
//     }, 1000);
// }, 1000);

import {Id, toast} from 'react-toastify';
import {useUploadStore} from '../store/uploadStore.ts';
import {useEffect, useRef} from 'react';
import {useModals} from '@alchemy/navigation';
import {Trans} from 'react-i18next';
import PendingUploadsDialog from '../components/Upload/PendingUploadsDialog.tsx';
import {ToastProgressStatus, upsertProgressiveToast} from '../lib/toast.ts';

export function usePendingUploads() {
    const uploads = useUploadStore(state => state.uploads);
    const toastId = useRef<Id | null>(null);
    const {openModal} = useModals();

    useEffect(() => {
        if (uploads.length === 0) {
            if (toastId.current !== null) {
                const tid = toastId.current;
                toast.done(tid);
                toastId.current = null;
            }

            return;
        }

        const pendingUploads = uploads.filter(u => u.progress < 1 && !u.error);
        const total = uploads.reduce((acc, u) => acc + u.file.size, 0);
        const progress =
            uploads.reduce(
                (acc, u) => acc + u.file.size * u.progress * 100,
                0
            ) /
            (total || 1) /
            100;
        const errored = uploads.some(u => u.error);

        const message = (
            <Trans
                i18nKey={'upload.pending.toast.message'}
                values={{uploaded: uploads.length - pendingUploads.length}}
                count={uploads.length}
                defaults={`<strong>{{uploaded}} / {{count}}</strong>  uploaded`}
                tOptions={{
                    defaultValue_other: `<strong>{{uploaded}} / {{count}}</strong>  uploaded`,
                }}
            />
        );

        const isLoading = progress < 1;

        toastId.current = upsertProgressiveToast(toastId.current, {
            render: message,
            onClose: () => {
                toastId.current = null;
            },
            autoClose: false,
            status: errored
                ? ToastProgressStatus.Error
                : isLoading
                  ? ToastProgressStatus.InProgress
                  : ToastProgressStatus.Done,
            progress,
            onClick: () => {
                openModal(PendingUploadsDialog);
            },
        });
    }, [uploads, toastId]);
}

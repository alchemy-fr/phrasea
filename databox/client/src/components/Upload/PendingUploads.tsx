import {useUploadStore} from '../../store/uploadStore.ts';
import {useEffect, useRef} from 'react';
import {Id, toast} from 'react-toastify';
import {Trans} from 'react-i18next';
import PendingUploadsDialog from './PendingUploadsDialog.tsx';
import {useModals} from '@alchemy/navigation';

type Props = {};

export default function PendingUploads({}: Props) {
    const uploads = useUploadStore(state => state.uploads);
    const toastId = useRef<Id | null>(null);
    const {openModal} = useModals();

    useEffect(() => {
        if (uploads.length === 0) {
            if (toastId.current !== null) {
                const tid = toastId.current;
                setTimeout(() => {
                    toast.done(tid);
                }, 2000);
                toastId.current = null;
            }
            return;
        }

        const pendingUploads = uploads.filter(upload => upload.progress < 1);
        const progress =
            uploads.reduce((acc, upload) => acc + upload.progress, 0) /
            uploads.length;
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

        if (toastId.current === null) {
            toastId.current = toast.info(message, {
                progress,
                isLoading: true,
                closeButton: false,
                autoClose: false,
                onClick: () => {
                    openModal(PendingUploadsDialog);
                },
            });
        } else {
            const isLoading = progress < 1;
            toast.update(toastId.current, {
                progress: isLoading ? progress : undefined,
                isLoading,
                autoClose: isLoading ? false : null,
                closeButton: !isLoading,
                render: message,
            });
        }
    }, [uploads, toastId]);

    return null;
}

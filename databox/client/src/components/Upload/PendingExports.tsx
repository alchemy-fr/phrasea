import {useEffect, useRef} from 'react';
import {Id, toast} from 'react-toastify';
import {Trans} from 'react-i18next';
import {useAssetExportStore} from '../../store/assetExportStore.ts';
import {ExportStatusEnum} from '../../types.ts';

type Props = {};

export default function PendingExports({}: Props) {
    const data = useAssetExportStore(state => state.data);
    const toastIds = useRef<Record<string, Id>>({});

    useEffect(() => {
        if (data.length === 0) {
            Object.entries(toastIds.current).map(([id, toastId]) => {
                toast.done(toastId);
                delete toastIds.current[id];
            });

            return;
        }

        data.forEach(exp => {
            toastIds.current[exp.id] = toast.info(
                <Trans
                    i18nKey={'asset_export.pending.toast.message'}
                    values={{progress: Math.round((exp.progress ?? 0) * 100)}}
                    defaults={`Export {{progress}}%`}
                />,
                {
                    progress: exp.progress,
                    isLoading: exp.status === ExportStatusEnum.Pending,
                    closeButton: false,
                    autoClose: false,
                    onClose: () => {
                        delete toastIds.current[exp.id];
                    },
                }
            );
        });
    }, [data]);

    return null;
}

import {Id, toast} from 'react-toastify';
import {useEffect, useRef} from 'react';
import {Trans} from 'react-i18next';
import {useAssetExportStore} from '../store/assetExportStore.ts';
import {ExportStatusEnum} from '../types.ts';
import {Button} from '@mui/material';
import DownloadIcon from '@mui/icons-material/Download';
import {ToastProgressStatus, upsertProgressiveToast} from '../lib/toast.ts';

export function usePendingExports() {
    const data = useAssetExportStore(state => state.data);
    const removeExport = useAssetExportStore(state => state.removeExport);
    const toastIds = useRef<Record<string, Id>>({});

    useEffect(() => {
        if (data.length === 0) {
            Object.entries(toastIds.current).map(([id, toastId]) => {
                toast.dismiss(toastId);
                delete toastIds.current[id];
            });

            return;
        }

        data.forEach(exp => {
            const progress = exp.progress ?? 0;

            const statusMap: Record<ExportStatusEnum, ToastProgressStatus> = {
                [ExportStatusEnum.Pending]: ToastProgressStatus.InProgress,
                [ExportStatusEnum.InProgress]: ToastProgressStatus.InProgress,
                [ExportStatusEnum.Ready]: ToastProgressStatus.Done,
                [ExportStatusEnum.Failed]: ToastProgressStatus.Error,
            };

            toastIds.current[exp.id] = upsertProgressiveToast(
                toastIds.current[exp.id],
                {
                    status: statusMap[exp.status],
                    progress,
                    render:
                        exp.status === ExportStatusEnum.Failed && exp.error ? (
                            exp.error
                        ) : exp.status === ExportStatusEnum.Ready ? (
                            <Trans
                                i18nKey={'asset_export.pending.toast.done'}
                                values={{
                                    progress: Math.round(progress * 100),
                                }}
                                components={{
                                    a: (
                                        <Button
                                            variant={'contained'}
                                            color={'primary'}
                                            href={exp.downloadUrl!}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            startIcon={<DownloadIcon />}
                                            sx={{
                                                mx: 1,
                                            }}
                                        />
                                    ),
                                }}
                                defaults={`Export Ready! <a>Download</a>`}
                            />
                        ) : (
                            <Trans
                                i18nKey={
                                    'asset_export.pending.toast.in_progress'
                                }
                                values={{
                                    progress: Math.round(progress * 100),
                                }}
                                defaults={`Preparing Export… {{progress}}%`}
                            />
                        ),
                    autoClose: false,
                    onClose: () => {
                        delete toastIds.current[exp.id];
                        removeExport(exp.id);
                    },
                }
            );
        });
    }, [data]);
}

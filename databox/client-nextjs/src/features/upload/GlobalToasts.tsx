'use client';

import {useEffect, useRef} from 'react';
import {toast} from 'sonner';
import {useTranslation} from 'react-i18next';
import {DownloadIcon, UploadIcon} from 'lucide-react';
import {useUploadStore} from './uploadStore';
import {useExportStore} from '@/features/assets/exportStore';
import {ExportStatus} from '@/types/api';
import {Button} from '@/components/ui/button';
import {Progress} from '@/components/ui/misc';
import {useModals} from '@/components/modals/ModalProvider';
import {PendingUploadsDialog} from './PendingUploadsDialog';

/**
 * Persistent progress toasts for running uploads and exports.
 */
export function GlobalToasts() {
    const {t} = useTranslation();
    const uploads = useUploadStore(s => s.uploads);
    const exportsData = useExportStore(s => s.exports);
    const removeExport = useExportStore(s => s.remove);
    const {openModal} = useModals();
    const uploadToastId = useRef<string | number | undefined>(undefined);
    const exportToasts = useRef<Record<string, string | number>>({});

    useEffect(() => {
        const pending = uploads.filter(u => !u.error);
        const done = pending.filter(u => u.progress >= 1).length;
        const total = pending.length;
        const errors = uploads.filter(u => u.error).length;

        if (total === 0 && errors === 0) {
            if (uploadToastId.current !== undefined) {
                toast.dismiss(uploadToastId.current);
                uploadToastId.current = undefined;
            }

            return;
        }
        const progress =
            total > 0
                ? pending.reduce((acc, u) => acc + u.progress, 0) / total
                : 1;
        const finished = done === total;

        uploadToastId.current = toast.custom(
            () => (
                <button
                    type="button"
                    className="w-80 rounded-lg border bg-popover p-3 text-left text-sm shadow-lg"
                    onClick={() => openModal(PendingUploadsDialog, {})}
                >
                    <div className="mb-2 flex items-center gap-2 font-medium">
                        <UploadIcon className="size-4" />
                        {finished
                            ? t('upload.toast.done', 'Upload complete')
                            : t(
                                  'upload.toast.progress',
                                  '{{done}} / {{total}} uploaded',
                                  {
                                      done,
                                      total,
                                  }
                              )}
                        {errors > 0 ? (
                            <span className="ml-auto text-destructive">
                                {t('upload.toast.errors', '{{count}} failed', {
                                    count: errors,
                                })}
                            </span>
                        ) : null}
                    </div>
                    <Progress value={Math.round(progress * 100)} />
                </button>
            ),
            {
                id: uploadToastId.current ?? 'uploads',
                duration: finished ? 6000 : Infinity,
            }
        );
    }, [uploads, t, openModal]);

    useEffect(() => {
        for (const exp of exportsData) {
            const progress = Math.round((exp.progress ?? 0) * 100);
            const ready = exp.status === ExportStatus.Ready;
            const failed = exp.status === ExportStatus.Failed;
            exportToasts.current[exp.id] = toast.custom(
                () => (
                    <div className="w-80 rounded-lg border bg-popover p-3 text-sm shadow-lg">
                        <div className="mb-2 flex items-center gap-2 font-medium">
                            <DownloadIcon className="size-4" />
                            {failed
                                ? (exp.error ??
                                  t('export.toast.failed', 'Export failed'))
                                : ready
                                  ? t('export.toast.ready', 'Export ready!')
                                  : t(
                                        'export.toast.progress',
                                        'Preparing export… {{progress}}%',
                                        {
                                            progress,
                                        }
                                    )}
                        </div>
                        {ready && exp.downloadUrl ? (
                            <Button asChild size="sm">
                                <a
                                    href={exp.downloadUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <DownloadIcon />
                                    {t('export.toast.download', 'Download')}
                                </a>
                            </Button>
                        ) : !failed ? (
                            <Progress
                                value={progress}
                                indeterminate={!exp.progress}
                            />
                        ) : null}
                    </div>
                ),
                {
                    id: exportToasts.current[exp.id] ?? `export-${exp.id}`,
                    duration: ready || failed ? 15000 : Infinity,
                    onDismiss: () => removeExport(exp.id),
                    onAutoClose: () => removeExport(exp.id),
                }
            );
        }
    }, [exportsData, t, removeExport]);

    return null;
}

'use client';

import {useTranslation} from 'react-i18next';
import {CheckCircle2Icon, XIcon} from 'lucide-react';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Button} from '@/components/ui/button';
import {Badge, Progress} from '@/components/ui/misc';
import {useUploadStore} from './uploadStore';
import {formatFileSize} from '@/lib/utils/format';

export function PendingUploadsDialog({open, onOpenChange}: ModalProps) {
    const {t, i18n} = useTranslation();
    const uploads = useUploadStore(s => s.uploads);
    const remove = useUploadStore(s => s.remove);
    const clearFinished = useUploadStore(s => s.clearFinished);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t('upload.pending.title', 'Pending uploads')}
            hideCancel
            submitLabel={t('common.close', 'Close')}
            bodyClassName="space-y-2"
            footerStart={
                <Button variant="outline" onClick={clearFinished}>
                    {t('upload.pending.clear', 'Clear finished')}
                </Button>
            }
        >
            {uploads.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">
                    {t('upload.pending.empty', 'No pending upload')}
                </p>
            ) : (
                uploads.map(u => (
                    <div key={u.id} className="rounded-md border p-3">
                        <div className="mb-2 flex items-center gap-2 text-sm">
                            <span className="min-w-0 flex-1 truncate font-medium">
                                {u.file.name}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {formatFileSize(
                                    u.file.size,
                                    true,
                                    i18n.language
                                )}
                            </span>
                            {u.error ? (
                                <Badge variant="destructive">
                                    {t('upload.pending.failed', 'Failed')}
                                </Badge>
                            ) : u.progress >= 1 ? (
                                <Badge variant="success">
                                    <CheckCircle2Icon />{' '}
                                    {t('upload.pending.uploaded', 'Uploaded')}
                                </Badge>
                            ) : (
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={() => {
                                        u.abort?.();
                                        remove(u.id);
                                    }}
                                    aria-label={t('common.cancel', 'Cancel')}
                                >
                                    <XIcon />
                                </Button>
                            )}
                        </div>
                        {u.error ? (
                            <p className="text-xs text-destructive">
                                {u.error}
                            </p>
                        ) : (
                            <Progress value={Math.round(u.progress * 100)} />
                        )}
                    </div>
                ))
            )}
        </FormDialog>
    );
}

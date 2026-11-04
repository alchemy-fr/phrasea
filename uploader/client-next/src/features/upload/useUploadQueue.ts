'use client';

import {useEffect, useState, useSyncExternalStore} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {
    UploadQueue,
    OFFLINE_MESSAGE,
    type UploadItem,
} from './engine/UploadQueue';
import {createUploadFile} from './engine/uploadFile';
import {getFileKey, ResumeStorage} from './engine/resumeStorage';

/** Message shown for a file of the batch */
export function useUploadErrorMessage(): (
    item: UploadItem
) => string | undefined {
    const {t} = useTranslation();

    return item =>
        item.error === OFFLINE_MESSAGE
            ? t(
                  'upload.offline',
                  'Your connection seems offline. Upload will resume automatically when back online!'
              )
            : item.error;
}

/**
 * The upload queue of the wizard, bound to the target and to the user (the
 * resumable uploads are remembered per user). Its transfers stop when the
 * screen unmounts.
 */
export function useUploadQueue(targetId: string, userKey: string) {
    // The instance, not `t`: the callbacks outlive the render they were
    // created in, and follow the language changes
    const {i18n} = useTranslation();
    const [queue] = useState(() => {
        const t = i18n.t.bind(i18n);
        const resumeStorage = new ResumeStorage(userKey);

        return new UploadQueue({
            uploadFile: createUploadFile({
                targetId,
                resumeStorage,
                onRetry: (file, notice) => {
                    const id = `upload-retry-${getFileKey(file)}`;
                    if (!notice) {
                        toast.dismiss(id);

                        return;
                    }
                    toast.warning(
                        t(
                            'upload.retrying',
                            'Upload failed. Retrying attempt {{attempt}} in {{seconds}}s…',
                            {
                                attempt: notice.attempt,
                                seconds: Math.round(notice.delayMs / 1000),
                            }
                        ),
                        {id, description: file.name, duration: Infinity}
                    );
                },
            }),
            onError: item =>
                toast.error(
                    t('upload.file_failed', 'Upload of {{name}} failed', {
                        name: item.file.name,
                    }),
                    {description: item.error}
                ),
        });
    });

    useEffect(() => queue.watchOnline(), [queue]);
    useEffect(() => () => queue.cancel(), [queue]);

    const snapshot = useSyncExternalStore(
        queue.subscribe,
        queue.getSnapshot,
        queue.getSnapshot
    );

    return {queue, snapshot};
}

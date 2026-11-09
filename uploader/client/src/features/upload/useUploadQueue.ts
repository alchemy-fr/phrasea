'use client';

import {useEffect, useState, useSyncExternalStore} from 'react';
import {useTranslation} from 'react-i18next';
import type {TFunction} from 'i18next';
import {toast} from 'sonner';
import type {RetryNotice} from '@/lib/api/upload';
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
                  'Connection lost. The upload will resume on its own as soon as it is back.'
              )
            : item.error;
}

const RETRY_TOAST_ID = 'upload-retry';

/**
 * A single toast for the files whose transfer is being retried, however many
 * they are.
 */
function createRetryToast(t: TFunction) {
    const notices = new Map<string, {name: string; notice: RetryNotice}>();

    const render = () => {
        const entries = [...notices.values()];
        if (entries.length === 0) {
            toast.dismiss(RETRY_TOAST_ID);

            return;
        }
        const [{name, notice}] = entries;
        toast.warning(
            entries.length === 1
                ? t(
                      'upload.retrying',
                      'Unstable connection: retrying in {{seconds}}s (attempt {{attempt}})',
                      {
                          attempt: notice.attempt,
                          seconds: Math.round(notice.delayMs / 1000),
                      }
                  )
                : t('upload.retrying_many', {
                      defaultValue_one:
                          'Unstable connection: {{count}} file is about to be retried',
                      defaultValue_other:
                          'Unstable connection: {{count}} files are about to be retried',
                      count: entries.length,
                  }),
            {
                id: RETRY_TOAST_ID,
                description:
                    entries.length === 1
                        ? name
                        : entries.map(e => e.name).join(', '),
                duration: Infinity,
            }
        );
    };

    return {
        set(file: File, notice: RetryNotice | null) {
            const key = getFileKey(file);
            if (notice) {
                notices.set(key, {name: file.name, notice});
            } else if (!notices.delete(key)) {
                return;
            }
            render();
        },
        /** Forgets the files no longer being sent (removed, cancelled…) */
        keepOnly(files: File[]) {
            const keys = new Set(files.map(getFileKey));
            let changed = false;
            for (const key of [...notices.keys()]) {
                if (!keys.has(key)) {
                    notices.delete(key);
                    changed = true;
                }
            }
            if (changed) {
                render();
            }
        },
    };
}

/**
 * The upload queue of the wizard, bound to the target and to the user (the
 * resumable uploads are remembered per user). Its transfers stop when the
 * screen unmounts. Failures are shown by the wizard itself, not as toasts.
 */
export function useUploadQueue(targetId: string, userKey: string) {
    // The instance, not `t`: the callbacks outlive the render they were
    // created in, and follow the language changes
    const {i18n} = useTranslation();
    const [{queue, retryToast}] = useState(() => {
        const retryToast = createRetryToast(i18n.t.bind(i18n) as TFunction);
        const resumeStorage = new ResumeStorage(userKey);

        return {
            retryToast,
            queue: new UploadQueue({
                uploadFile: createUploadFile({
                    targetId,
                    resumeStorage,
                    onRetry: (file, notice) => retryToast.set(file, notice),
                }),
            }),
        };
    });

    useEffect(() => queue.watchOnline(), [queue]);
    useEffect(() => () => queue.cancel(), [queue]);
    useEffect(() => {
        const unsubscribe = queue.subscribe(() =>
            retryToast.keepOnly(
                queue
                    .getSnapshot()
                    .items.filter(
                        i => i.status === 'uploading' || i.status === 'retrying'
                    )
                    .map(i => i.file)
            )
        );

        return () => {
            unsubscribe();
            retryToast.keepOnly([]);
        };
    }, [queue, retryToast]);

    const snapshot = useSyncExternalStore(
        queue.subscribe,
        queue.getSnapshot,
        queue.getSnapshot
    );

    return {queue, snapshot};
}

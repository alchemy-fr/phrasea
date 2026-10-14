'use client';

import {useCallback, useEffect, useRef, useState} from 'react';
import {multipartUpload, type MultipartUpload} from '@/lib/api/upload';
import {isAbortError} from '@/lib/api/http';
import {FILE_ATTACHMENT, type FileAttachmentInput} from './messageAttachments';

export type PendingAttachment = {
    key: string;
    name: string;
    type: string;
    size: number;
    /** 0 to 1 */
    progress: number;
    status: 'uploading' | 'done' | 'error';
    error?: string;
    /** Object URL of an image, for its thumbnail */
    preview?: string;
    multipart?: MultipartUpload;
};

let counter = 0;

/**
 * Files attached to the message being written: each one is uploaded right
 * away (multipart upload), the message links the completed uploads.
 */
export function usePendingAttachments() {
    const [items, setItems] = useState<PendingAttachment[]>([]);
    const controllers = useRef(new Map<string, AbortController>());
    const previews = useRef(new Map<string, string>());

    const update = useCallback(
        (key: string, patch: Partial<PendingAttachment>) =>
            setItems(list =>
                list.map(i => (i.key === key ? {...i, ...patch} : i))
            ),
        []
    );

    const release = useCallback((key: string) => {
        controllers.current.get(key)?.abort();
        controllers.current.delete(key);
        const preview = previews.current.get(key);
        if (preview) {
            URL.revokeObjectURL(preview);
            previews.current.delete(key);
        }
    }, []);

    const add = useCallback(
        (files: Iterable<File>) => {
            for (const file of files) {
                const key = `att-${++counter}`;
                const controller = new AbortController();
                controllers.current.set(key, controller);
                let preview: string | undefined;
                if (file.type.startsWith('image/')) {
                    preview = URL.createObjectURL(file);
                    previews.current.set(key, preview);
                }
                setItems(list => [
                    ...list,
                    {
                        key,
                        name: file.name,
                        type: file.type,
                        size: file.size,
                        progress: 0,
                        status: 'uploading',
                        preview,
                    },
                ]);
                multipartUpload(file, {
                    signal: controller.signal,
                    onProgress: ({loaded, total}) =>
                        update(key, {progress: total ? loaded / total : 1}),
                }).then(
                    multipart => {
                        controllers.current.delete(key);
                        update(key, {status: 'done', progress: 1, multipart});
                    },
                    (e: unknown) => {
                        controllers.current.delete(key);
                        if (!isAbortError(e)) {
                            update(key, {
                                status: 'error',
                                error:
                                    e instanceof Error ? e.message : String(e),
                            });
                        }
                    }
                );
            }
        },
        [update]
    );

    const remove = useCallback(
        (key: string) => {
            release(key);
            setItems(list => list.filter(i => i.key !== key));
        },
        [release]
    );

    const clear = useCallback(() => {
        for (const key of [...controllers.current.keys()]) {
            release(key);
        }
        for (const key of [...previews.current.keys()]) {
            release(key);
        }
        setItems([]);
    }, [release]);

    // Pending uploads are aborted and previews released on unmount
    useEffect(() => {
        const c = controllers.current;
        const p = previews.current;

        return () => {
            c.forEach(ctrl => ctrl.abort());
            p.forEach(url => URL.revokeObjectURL(url));
        };
    }, []);

    const inputs: FileAttachmentInput[] = items.flatMap(i =>
        i.status === 'done' && i.multipart
            ? [{type: FILE_ATTACHMENT, multipart: i.multipart}]
            : []
    );

    return {
        items,
        add,
        remove,
        clear,
        uploading: items.some(i => i.status === 'uploading'),
        failed: items.some(i => i.status === 'error'),
        inputs,
    };
}

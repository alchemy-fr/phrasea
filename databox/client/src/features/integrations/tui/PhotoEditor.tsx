'use client';

import {type RefObject, useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import 'tui-image-editor/dist/tui-image-editor.css';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {EmptyState} from '@alchemy/next-ui/ui/misc';

/** The bits of the Toast UI editor instance this feature uses. */
export type PhotoEditorInstance = {
    toDataURL: (options?: {format?: string; quality?: number}) => string;
    destroy: () => void;
};

/**
 * Downloads the image into a same-origin `blob:` URL, or returns undefined
 * when the URL is already local (`data:`, `blob:`).
 *
 * The signed file URL is the very one the preview loads through a plain
 * `<img>` (no CORS): the storage answers it without `Vary: Origin`, so the
 * browser may serve that cached, non-CORS response to the editor, which loads
 * its image with `crossOrigin` (it reads the canvas back on export) and gets
 * it blocked. A CORS request bypassing the HTTP cache avoids it, and a `blob:`
 * URL keeps the canvas untainted.
 */
async function fetchImage(
    url: string,
    signal: AbortSignal
): Promise<string | undefined> {
    if (url.startsWith('data:') || url.startsWith('blob:')) {
        return undefined;
    }

    const res = await fetch(url, {
        mode: 'cors',
        credentials: 'omit',
        cache: 'no-store',
        signal,
    });
    if (!res.ok) {
        throw new Error(`The image could not be loaded (HTTP ${res.status})`);
    }

    return URL.createObjectURL(await res.blob());
}

/**
 * Toast UI photo editor on a file.
 *
 * The library draws its own (imperative, canvas based) UI and touches
 * `document` as soon as it is loaded, so it is imported on demand, in the
 * browser only, and kept out of the server bundle.
 */
export function PhotoEditor({
    url,
    name,
    editorRef,
}: {
    url: string;
    /** Name the editor shows, and suggests when downloading */
    name: string;
    editorRef: RefObject<PhotoEditorInstance | null>;
}) {
    const {t} = useTranslation();
    const container = useRef<HTMLDivElement>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string>();

    useEffect(() => {
        let instance: PhotoEditorInstance | undefined;
        let objectUrl: string | undefined;
        let cancelled = false;
        const abortController = new AbortController();
        const revokeObjectUrl = () => {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = undefined;
            }
        };

        void (async () => {
            try {
                const [mod] = await Promise.all([
                    import('tui-image-editor'),
                    fetchImage(url, abortController.signal).then(u => {
                        objectUrl = u;
                    }),
                ]);
                const ImageEditor = (mod.default ?? mod) as new (
                    el: HTMLElement,
                    options: object
                ) => PhotoEditorInstance;
                if (cancelled || !container.current) {
                    revokeObjectUrl();
                    return;
                }
                instance = new ImageEditor(container.current, {
                    includeUI: {
                        loadImage: {path: objectUrl ?? url, name},
                        initMenu: 'filter',
                        menuBarPosition: 'bottom',
                        uiSize: {width: '100%', height: '100%'},
                    },
                    selectionStyle: {
                        cornerSize: 20,
                        rotatingPointOffset: 70,
                    },
                    usageStatistics: false,
                });
                editorRef.current = instance;
                setLoading(false);
            } catch (e: unknown) {
                if (cancelled) {
                    revokeObjectUrl();
                    return;
                }
                setError(
                    e instanceof Error ? e.message : 'Photo editor failed'
                );
            }
        })();

        return () => {
            cancelled = true;
            abortController.abort();
            editorRef.current = null;
            instance?.destroy();
            // The editor reloads the image from this URL on "Reset": it can
            // only go once the editor is gone
            revokeObjectUrl();
        };
    }, [url, name, editorRef]);

    if (error) {
        return (
            <EmptyState
                title={t(
                    'tui_photo_editor.load_error',
                    'The photo editor could not be loaded'
                )}
                description={error}
            />
        );
    }

    return (
        <div className="relative size-full">
            {loading ? <FullPageLoader /> : null}
            {/* The editor sizes itself on this element */}
            <div ref={container} className="size-full" />
        </div>
    );
}

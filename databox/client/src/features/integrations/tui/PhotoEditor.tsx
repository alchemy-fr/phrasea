'use client';

import {type RefObject, useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import 'tui-image-editor/dist/tui-image-editor.css';
import {FullPageLoader} from '@/components/ui/loader';
import {EmptyState} from '@/components/ui/misc';

/** The bits of the Toast UI editor instance this feature uses. */
export type PhotoEditorInstance = {
    toDataURL: (options?: {format?: string; quality?: number}) => string;
    destroy: () => void;
};

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
        let cancelled = false;

        void (async () => {
            try {
                const mod = await import('tui-image-editor');
                const ImageEditor = (mod.default ?? mod) as new (
                    el: HTMLElement,
                    options: object
                ) => PhotoEditorInstance;
                if (cancelled || !container.current) {
                    return;
                }
                instance = new ImageEditor(container.current, {
                    includeUI: {
                        loadImage: {path: url, name},
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
                setError(
                    e instanceof Error ? e.message : 'Photo editor failed'
                );
            }
        })();

        return () => {
            cancelled = true;
            editorRef.current = null;
            instance?.destroy();
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

'use client';

import {
    createContext,
    ReactNode,
    useContext,
    useEffect,
    useState,
    useSyncExternalStore,
} from 'react';
import {
    FileKind,
    getFileKind,
    getMimeTypeFromFile,
} from '@alchemy/next-ui/utils/mime';
import {createThumbnail} from './thumbnail';

/** Images larger than this are not previewed: decoding them costs too much */
export const PREVIEW_MAX_SIZE = 15 * 1024 * 1024;

/** Side of the thumbnails, twice the width of a tile for dense screens */
const THUMBNAIL_SIZE = 288;

/** Images decoded at the same time to make their thumbnail */
const MAX_CONCURRENT = 2;

/**
 * The thumbnail of a file: its object URL, `undefined` while it is made or
 * when the file is not an image to preview, `null` when the browser could not
 * decode the image.
 */
export type Preview = string | null | undefined;

type Entry = {url: Preview};

function isPreviewable(file: File): boolean {
    return (
        getFileKind(getMimeTypeFromFile(file)) === FileKind.Image &&
        file.size < PREVIEW_MAX_SIZE
    );
}

/**
 * The thumbnails of the images of the upload, made as soon as the files are
 * selected and kept for the whole upload, so that a tile shown again (files
 * hidden then shown, next step…) displays its thumbnail at once.
 */
export class PreviewCache {
    private entries = new Map<File, Entry>();
    private listeners = new Set<() => void>();
    private queue: (() => Promise<void>)[] = [];
    private running = 0;

    get(file: File): Preview {
        return this.entries.get(file)?.url;
    }

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);

        return () => {
            this.listeners.delete(listener);
        };
    };

    /** Keeps the thumbnails of `files`, makes the missing ones, releases the others */
    sync(files: Iterable<File>): void {
        const kept = new Set(files);
        this.entries.forEach((entry, file) => {
            if (!kept.has(file)) {
                this.release(file, entry);
            }
        });
        kept.forEach(file => this.load(file));
    }

    markBroken(file: File): void {
        const entry = this.entries.get(file);
        if (!entry) {
            return;
        }
        if (entry.url) {
            URL.revokeObjectURL(entry.url);
        }
        entry.url = null;
        this.emit();
    }

    clear(): void {
        this.entries.forEach((entry, file) => this.release(file, entry));
        this.queue = [];
    }

    private load(file: File): void {
        if (this.entries.has(file) || !isPreviewable(file)) {
            return;
        }
        const entry: Entry = {url: undefined};
        this.entries.set(file, entry);
        const current = () => this.entries.get(file) === entry;

        this.queue.push(async () => {
            if (!current()) {
                return;
            }
            let url: string | null;
            try {
                url = URL.createObjectURL(
                    await createThumbnail(file, THUMBNAIL_SIZE)
                );
            } catch {
                url = null;
            }
            if (!current()) {
                if (url) {
                    URL.revokeObjectURL(url);
                }

                return;
            }
            entry.url = url;
            this.emit();
        });
        this.next();
    }

    private next(): void {
        while (this.running < MAX_CONCURRENT && this.queue.length > 0) {
            const task = this.queue.shift()!;
            this.running++;
            void task().finally(() => {
                this.running--;
                this.next();
            });
        }
    }

    private release(file: File, entry: Entry): void {
        if (entry.url) {
            URL.revokeObjectURL(entry.url);
        }
        this.entries.delete(file);
    }

    private emit(): void {
        this.listeners.forEach(listener => listener());
    }
}

const PreviewCacheContext = createContext<PreviewCache | null>(null);

/** Keeps the thumbnails of `files` until they leave the list or on unmount */
export function PreviewCacheProvider({
    files,
    children,
}: {
    files: File[];
    children: ReactNode;
}) {
    const [cache] = useState(() => new PreviewCache());

    useEffect(() => {
        cache.sync(files);
    }, [cache, files]);

    useEffect(() => () => cache.clear(), [cache]);

    return (
        <PreviewCacheContext.Provider value={cache}>
            {children}
        </PreviewCacheContext.Provider>
    );
}

/** The thumbnail of the file, and a callback to give up on it */
export function usePreview(file: File): [Preview, () => void] {
    const cache = useContext(PreviewCacheContext);
    if (!cache) {
        throw new Error('usePreview must be used in a PreviewCacheProvider');
    }
    const preview = useSyncExternalStore(
        cache.subscribe,
        () => cache.get(file),
        () => undefined
    );

    return [preview, () => cache.markBroken(file)];
}

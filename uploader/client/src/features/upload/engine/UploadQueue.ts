import {isAbortError, isApiError} from '@/lib/api/http';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';

export type UploadStatus =
    'queued' | 'uploading' | 'retrying' | 'offline' | 'error' | 'done';

export type UploadItem = {
    id: string;
    file: File;
    status: UploadStatus;
    /** Bytes sent so far */
    loaded: number;
    /** Failed attempts of the current run */
    attempts: number;
    error?: string;
    /** Id of the uploader asset, once the file is complete */
    assetId?: string;
};

export type QueueSnapshot = {
    items: UploadItem[];
    loaded: number;
    total: number;
    /** Every file is uploaded (and there is at least one) */
    complete: boolean;
    /** Some file failed for good: it must be retried or removed */
    failed: number;
};

export type UploadFileFn = (
    file: File,
    context: {signal: AbortSignal; onProgress: (loaded: number) => void}
) => Promise<{assetId: string}>;

export type UploadQueueOptions = {
    uploadFile: UploadFileFn;
    /** Files sent at the same time */
    concurrency?: number;
    /** Automatic attempts of a file before it is marked as failed */
    maxAttempts?: number;
    isOnline?: () => boolean;
    /** Delay before the n-th automatic retry of a file (n >= 1) */
    retryDelay?: (attempt: number) => number;
    /** A file failed for good */
    onError?: (item: UploadItem) => void;
};

export const DEFAULT_FILE_CONCURRENCY = 2;
export const DEFAULT_MAX_ATTEMPTS = 5;

export const OFFLINE_MESSAGE = 'offline';

type Running = {
    controller: AbortController;
    timer?: ReturnType<typeof setTimeout>;
};

/**
 * Failures retrying cannot fix: the API refused the file (bad request,
 * forbidden target, too large…). Rate limiting and timeouts are worth
 * another try.
 */
export function isFatalUploadError(e: unknown): boolean {
    return (
        isApiError(e) &&
        e.status >= 400 &&
        e.status < 500 &&
        e.status !== 408 &&
        e.status !== 429
    );
}

let sequence = 0;

/**
 * The files of a batch, sent a few at a time. A file that fails is retried
 * a few times, then left aside — it never blocks the others, and can be
 * retried or removed from the batch. Files cut by a lost connection wait for
 * the browser to get back online.
 */
export class UploadQueue {
    private items: UploadItem[] = [];
    private readonly running = new Map<string, Running>();
    private readonly listeners = new Set<() => void>();
    private snapshot: QueueSnapshot = buildSnapshot([]);
    private readonly concurrency: number;
    private readonly maxAttempts: number;
    private readonly isOnline: () => boolean;
    private readonly retryDelay: (attempt: number) => number;

    constructor(private readonly options: UploadQueueOptions) {
        this.concurrency = options.concurrency ?? DEFAULT_FILE_CONCURRENCY;
        this.maxAttempts = options.maxAttempts ?? DEFAULT_MAX_ATTEMPTS;
        this.isOnline =
            options.isOnline ??
            (() => typeof navigator === 'undefined' || navigator.onLine);
        this.retryDelay =
            options.retryDelay ??
            (attempt => Math.min(30_000, 1000 * 2 ** (attempt - 1)));
    }

    /**
     * Resumes the files cut by a lost connection when the browser gets back
     * online, until the returned function is called.
     */
    watchOnline(
        register: (callback: () => void) => () => void = listenOnline
    ): () => void {
        return register(() => this.resumeOffline());
    }

    // -- Store --------------------------------------------------------------

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    };

    getSnapshot = (): QueueSnapshot => this.snapshot;

    private emit(): void {
        this.snapshot = buildSnapshot(this.items);
        this.listeners.forEach(l => l());
    }

    private update(id: string, patch: Partial<UploadItem>): void {
        this.items = this.items.map(i => (i.id === id ? {...i, ...patch} : i));
        this.emit();
    }

    private find(id: string): UploadItem | undefined {
        return this.items.find(i => i.id === id);
    }

    // -- Actions ------------------------------------------------------------

    add(files: File[]): void {
        if (files.length === 0) {
            return;
        }
        this.items = [
            ...this.items,
            ...files.map<UploadItem>(file => ({
                id: `upload-${++sequence}`,
                file,
                status: 'queued',
                loaded: 0,
                attempts: 0,
            })),
        ];
        this.emit();
        this.pump();
    }

    /** Sends a failed file again, from scratch of its attempts */
    retry(id: string): void {
        const item = this.find(id);
        if (!item || (item.status !== 'error' && item.status !== 'offline')) {
            return;
        }
        this.update(id, {status: 'queued', attempts: 0, error: undefined});
        this.pump();
    }

    /** Sends every failed file again */
    retryFailed(): void {
        if (!this.items.some(i => i.status === 'error')) {
            return;
        }
        this.items = this.items.map(i =>
            i.status === 'error'
                ? {...i, status: 'queued', attempts: 0, error: undefined}
                : i
        );
        this.emit();
        this.pump();
    }

    /** Drops every failed file from the batch; none of them is running */
    removeFailed(): void {
        const items = this.items.filter(i => i.status !== 'error');
        if (items.length !== this.items.length) {
            this.items = items;
            this.emit();
        }
    }

    /** Drops a file from the batch, stopping its transfer */
    remove(id: string): void {
        this.stop(id);
        this.items = this.items.filter(i => i.id !== id);
        this.emit();
        this.pump();
    }

    /** Stops every transfer and empties the batch */
    cancel(): void {
        [...this.running.keys()].forEach(id => this.stop(id));
        this.items = [];
        this.emit();
    }

    resumeOffline(): void {
        let changed = false;
        this.items = this.items.map(i => {
            if (i.status === 'offline') {
                changed = true;

                return {...i, status: 'queued', error: undefined};
            }

            return i;
        });
        if (changed) {
            this.emit();
            this.pump();
        }
    }

    // -- Scheduling ---------------------------------------------------------

    private stop(id: string): void {
        const running = this.running.get(id);
        if (running) {
            clearTimeout(running.timer);
            running.controller.abort();
            this.running.delete(id);
        }
    }

    private pump(): void {
        for (const item of this.items) {
            if (this.running.size >= this.concurrency) {
                return;
            }
            if (item.status === 'queued') {
                void this.run(item.id);
            }
        }
    }

    private async run(id: string): Promise<void> {
        const item = this.find(id);
        if (!item) {
            return;
        }
        const controller = new AbortController();
        this.running.set(id, {controller});
        this.update(id, {status: 'uploading', loaded: 0, error: undefined});

        try {
            const {assetId} = await this.options.uploadFile(item.file, {
                signal: controller.signal,
                onProgress: loaded => {
                    if (!controller.signal.aborted) {
                        this.update(id, {loaded});
                    }
                },
            });
            if (controller.signal.aborted) {
                return;
            }
            this.running.delete(id);
            this.update(id, {
                status: 'done',
                loaded: item.file.size,
                assetId,
            });
        } catch (e) {
            if (controller.signal.aborted || isAbortError(e)) {
                // Removed or cancelled on purpose: nothing to report
                if (this.running.get(id)?.controller === controller) {
                    this.running.delete(id);
                }

                return;
            }
            this.fail(id, controller, e);
        }
        this.pump();
    }

    private fail(id: string, controller: AbortController, e: unknown): void {
        const item = this.find(id);
        if (!item) {
            this.running.delete(id);

            return;
        }
        if (!this.isOnline()) {
            this.running.delete(id);
            this.update(id, {
                status: 'offline',
                loaded: 0,
                error: OFFLINE_MESSAGE,
            });

            return;
        }
        // What was sent of a failed file does not count: a new attempt
        // reports again what it resumes from
        const attempts = item.attempts + 1;
        const message = getErrorMessage(e);
        if (isFatalUploadError(e) || attempts >= this.maxAttempts) {
            this.running.delete(id);
            this.update(id, {
                status: 'error',
                loaded: 0,
                attempts,
                error: message,
            });
            this.options.onError?.(this.find(id)!);

            return;
        }

        // Keeps its slot while waiting: another file would only compete for
        // the same failing network
        const timer = setTimeout(() => {
            if (this.running.get(id)?.controller !== controller) {
                return;
            }
            this.running.delete(id);
            this.update(id, {status: 'queued'});
            this.pump();
        }, this.retryDelay(attempts));
        this.running.set(id, {controller, timer});
        this.update(id, {
            status: 'retrying',
            loaded: 0,
            attempts,
            error: message,
        });
    }
}

function buildSnapshot(items: UploadItem[]): QueueSnapshot {
    let loaded = 0;
    let total = 0;
    let failed = 0;
    for (const i of items) {
        total += i.file.size;
        loaded += i.status === 'done' ? i.file.size : i.loaded;
        if (i.status === 'error') {
            failed++;
        }
    }

    return {
        items,
        loaded,
        total,
        complete: items.length > 0 && items.every(i => i.status === 'done'),
        failed,
    };
}

function listenOnline(callback: () => void): () => void {
    if (typeof window === 'undefined') {
        return () => undefined;
    }
    window.addEventListener('online', callback);

    return () => window.removeEventListener('online', callback);
}

import type {ArchiveStatus} from '@/lib/api/publications';

const FIRST_RETRY_DELAY = 2_000;
const MAX_RETRY_DELAY = 15_000;

/**
 * How long the archive is waited for: the download URL given once the
 * download terms are accepted lives 5 minutes.
 */
export const ARCHIVE_MAX_WAIT = 180_000;

/** Delay before checking again, growing from 2 to 15 seconds */
export function getArchiveRetryDelay(attempt: number): number {
    return Math.min(
        MAX_RETRY_DELAY,
        Math.round(FIRST_RETRY_DELAY * 1.5 ** attempt)
    );
}

export type ArchiveWaitResult =
    {status: 'ready'; url: string} | {status: 'timeout'};

type WaitOptions = {
    signal?: AbortSignal;
    maxWait?: number;
    sleep?: (ms: number, signal?: AbortSignal) => Promise<void>;
    now?: () => number;
};

function sleepFor(ms: number, signal?: AbortSignal): Promise<void> {
    return new Promise((resolve, reject) => {
        if (signal?.aborted) {
            reject(signal.reason);

            return;
        }
        const timer = setTimeout(() => {
            signal?.removeEventListener('abort', abort);
            resolve();
        }, ms);
        const abort = () => {
            clearTimeout(timer);
            reject(signal!.reason);
        };
        signal?.addEventListener('abort', abort, {once: true});
    });
}

/**
 * Checks the archive until it is ready, less and less often, for
 * `maxWait` at most. Rejects when `signal` aborts.
 */
export async function waitForArchive(
    check: () => Promise<ArchiveStatus>,
    {
        signal,
        maxWait = ARCHIVE_MAX_WAIT,
        sleep = sleepFor,
        now = Date.now,
    }: WaitOptions = {}
): Promise<ArchiveWaitResult> {
    const start = now();
    for (let attempt = 0; ; attempt++) {
        const result = await check();
        if (result.status === 'ready') {
            return result;
        }
        const delay = getArchiveRetryDelay(attempt);
        if (now() - start + delay > maxWait) {
            return {status: 'timeout'};
        }
        await sleep(delay, signal);
    }
}

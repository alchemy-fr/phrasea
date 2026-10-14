/**
 * Cooperative interruption of a running indexation.
 *
 * SIGINT and SIGTERM ask the indexation to stop: parralelize() stops pulling
 * new assets, the ones already in flight finish, and consume() exits with the
 * usual 128+n code. A second signal exits at once, and so does the grace timer
 * for the commands that never finish on their own, such as `watch`.
 *
 * Nothing is rolled back, because nothing has to be: an indexation is
 * idempotent — assets and collections are looked up by key — so re-running it
 * resumes where it stopped.
 */

const GRACE_MS = 5000;

let requestedSignal: NodeJS.Signals | undefined;
const listeners: (() => void)[] = [];

/**
 * Registers something to release on interruption — an open server, a watcher.
 * Whatever still holds the event loop after the grace period below is killed
 * instead.
 */
export function onShutdown(listener: () => void): void {
    listeners.push(listener);
}

export function isShutdownRequested(): boolean {
    return undefined !== requestedSignal;
}

/** The shell convention: 130 for SIGINT, 143 for SIGTERM. */
export function shutdownExitCode(): number {
    return 'SIGINT' === requestedSignal ? 130 : 143;
}

export function handleShutdownSignal(signal: NodeJS.Signals): void {
    if (isShutdownRequested()) {
        // eslint-disable-next-line no-console
        console.error(`Received ${signal} again, exiting now.`);
        process.exit(shutdownExitCode());
    }

    requestedSignal = signal;

    // eslint-disable-next-line no-console
    console.error(
        `Received ${signal}, finishing what is in flight. Send it again to exit now.`
    );

    for (const listener of listeners) {
        try {
            listener();
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error(`Shutdown listener failed: ${(e as Error).message}`);
        }
    }

    // unref'd: it must not keep the process alive on its own, only cut short
    // the commands that would otherwise never return.
    setTimeout(() => {
        // eslint-disable-next-line no-console
        console.error(`Still running ${GRACE_MS}ms after ${signal}, exiting.`);
        process.exit(shutdownExitCode());
    }, GRACE_MS).unref();
}

export function registerShutdownHandlers(): void {
    for (const signal of ['SIGINT', 'SIGTERM'] as const) {
        process.on(signal, handleShutdownSignal);
    }
}

/** Tests only: the flag is process-wide and never cleared in production. */
export function clearShutdownRequest(): void {
    requestedSignal = undefined;
    listeners.length = 0;
}

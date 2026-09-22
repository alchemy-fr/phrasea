const promises: Record<string, Promise<any>> = {};

export function lockPromise<T>(
    key: string,
    handler: () => Promise<T>
): Promise<T> {
    // @ts-expect-error wrong TS interpretation
    if (promises[key]) {
        return promises[key];
    }

    return (promises[key] = handler());
}

/**
 * Drops every in-flight/resolved lock. Only used by tests: the cache is never
 * reset in production, where the process is short-lived.
 */
export function clearPromiseLocks(): void {
    for (const key of Object.keys(promises)) {
        delete promises[key];
    }
}


const promises: Record<string, Promise<any>> = {};

export function lockPromise<T>(key: string, handler: () => Promise<T>): Promise<T> {
    if (promises[key]) {
        return promises[key];
    }

    return promises[key] = handler();
}

type Waiter<V> = {
    resolve: (value: V | undefined) => void;
    reject: (error: unknown) => void;
};

/**
 * Groups the keys requested within `wait` ms into calls of `load` taking at
 * most `maxSize` keys each. A key missing from the result of `load` resolves
 * to `undefined`.
 */
export function createBatchLoader<V>({
    load,
    wait = 10,
    maxSize = 50,
}: {
    load: (keys: string[]) => Promise<Map<string, V>>;
    wait?: number;
    maxSize?: number;
}): (key: string) => Promise<V | undefined> {
    let pending = new Map<string, Waiter<V>[]>();
    let timer: ReturnType<typeof setTimeout> | undefined;

    const loadChunk = async (chunk: [string, Waiter<V>[]][]) => {
        try {
            const result = await load(chunk.map(([key]) => key));
            chunk.forEach(([key, waiters]) =>
                waiters.forEach(w => w.resolve(result.get(key)))
            );
        } catch (e) {
            chunk.forEach(([, waiters]) => waiters.forEach(w => w.reject(e)));
        }
    };

    const flush = () => {
        timer = undefined;
        const entries = [...pending];
        pending = new Map();
        for (let i = 0; i < entries.length; i += maxSize) {
            void loadChunk(entries.slice(i, i + maxSize));
        }
    };

    return key =>
        new Promise((resolve, reject) => {
            const waiters = pending.get(key);
            if (waiters) {
                waiters.push({resolve, reject});
            } else {
                pending.set(key, [{resolve, reject}]);
            }
            timer ??= setTimeout(flush, wait);
        });
}

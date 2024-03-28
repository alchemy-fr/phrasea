
export type StateSetter<T> = (handler: T | ((prev: T) => T)) => void;

export default function createStateSetterProxy<T>(
    handler: T | ((prev: T) => T),
    proxy: (newState: T) => T,
): (prev: T) => T {
    return p => {
        if (typeof handler === 'function') {
            const n = (handler as (prev: T) => T)(p);

            return proxy(n);
        }

        return proxy(handler);
    };
}

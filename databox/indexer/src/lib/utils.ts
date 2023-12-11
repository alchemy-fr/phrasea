
export function forceArray<D = any, T = undefined | null>(object: object | Array<D> | T): Array<D> | T {
    if (Array.isArray(object)) {
        return object;
    }

    if (typeof object === 'object') {
        // @ts-expect-error object can be null
        return Object.keys(object).map((k) => object[k]);
    }

    return object;
}

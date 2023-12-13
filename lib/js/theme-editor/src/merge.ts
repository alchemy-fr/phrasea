export function isObject(item: any): boolean {
    return item && typeof item === 'object' && !Array.isArray(item);
}

export function mergeDeep(target: object, ...sources: object[]): object {
    if (!sources.length) {
        return target;
    }

    const source: any = sources.shift();

    if (isObject(target) && isObject(source)) {
        for (const key in source! as object) {
            const e = source[key];
            if (isObject(e)) {
                // @ts-expect-error ?
                if (!target[key]) {
                    Object.assign(target, {[key]: {}});
                }
                // @ts-expect-error ?
                mergeDeep(target[key], e!);
            } else {
                Object.assign(target, {[key]: e!});
            }
        }
    }

    return mergeDeep(target, ...sources);
}

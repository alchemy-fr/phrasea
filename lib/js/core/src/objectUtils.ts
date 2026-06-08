export function deepEquals(a: any, b: any): boolean {
    if (a === b) return true;

    if (a == null || b == null) return false;

    if (typeof a !== typeof b) return false;

    if (a instanceof Date && b instanceof Date) {
        return a.getTime() === b.getTime();
    }

    if (Array.isArray(a) && Array.isArray(b)) {
        if (a.length !== b.length) return false;
        return a.every((item, i) => deepEquals(item, b[i]));
    }

    if (typeof a === 'object' && typeof b === 'object') {
        const keysA = Object.keys(a);
        const keysB = Object.keys(b);

        if (keysA.length !== keysB.length) {
            return false;
        }

        return keysA.every(
            key => keysB.includes(key) && deepEquals(a[key], b[key])
        );
    }

    return false;
}

export function forceObject(o: any): Record<string, any> {
    if (typeof o !== 'object' || Array.isArray(o)) {
        return {};
    }

    return o || {};
}

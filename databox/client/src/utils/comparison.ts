function isSameObject<T extends {} = {}>(a: T, b: T): boolean {
    const ka = Object.keys(a);
    const kb = Object.keys(b);

    if (ka.length !== kb.length) {
        return false;
    }

    for (const i of ka) {
        if (!isSame(b[i as keyof typeof b], a[i as keyof typeof a])) {
            return false;
        }
    }

    return true;
}

export function isSame(a: any, b: any): boolean {
    if (typeof a !== typeof b) {
        return false;
    }

    if (a instanceof Array && b instanceof Array) {
        return isSameArray(a, b);
    } else if (
        typeof a === 'object' &&
        typeof b === 'object' &&
        a !== null &&
        b !== null
    ) {
        return isSameObject(a, b);
    }

    return a === b;
}

function isSameArray(a: any[], b: any[]): boolean {
    if (!a) {
        return false;
    }

    if (b.length !== a.length) return false;

    for (let i = 0, l = b.length; i < l; i++) {
        if (b[i] instanceof Array && a[i] instanceof Array) {
            if (!isSameArray(b[i], a[i])) {
                return false;
            }
        } else if (!isSame(b[i], a[i])) {
            return false;
        }
    }
    return true;
}

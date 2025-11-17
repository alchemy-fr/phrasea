export function deepEquals(a: any, b: any): boolean {
    // Strict equality handles primitives and reference equality
    if (a === b) return true;

    // Handle null or undefined
    if (a == null || b == null) return false;

    // Check type mismatch
    if (typeof a !== typeof b) return false;

    // Handle Date objects
    if (a instanceof Date && b instanceof Date) {
        return a.getTime() === b.getTime();
    }

    // Handle Arrays
    if (Array.isArray(a) && Array.isArray(b)) {
        if (a.length !== b.length) return false;
        return a.every((item, i) => deepEquals(item, b[i]));
    }

    // Handle plain objects
    if (typeof a === 'object' && typeof b === 'object') {
        const keysA = Object.keys(a);
        const keysB = Object.keys(b);

        if (keysA.length !== keysB.length) return false;
        return keysA.every(
            key => keysB.includes(key) && deepEquals(a[key], b[key])
        );
    }

    // Fallback for other types (e.g., functions, symbols)
    return false;
}

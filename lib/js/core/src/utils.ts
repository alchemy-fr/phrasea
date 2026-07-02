export function isNotNull(value: any): boolean {
    return typeof value !== 'undefined' && value !== null;
}

export function nullToUndefined<T>(value: T | null | undefined): T | undefined {
    return null === value ? undefined : value;
}

export function isEmpty(value: any): boolean {
    return !isNotNull(value) || value === '';
}

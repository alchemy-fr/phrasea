export function isNotNull(value: any): boolean {
    return typeof value !== 'undefined' && value !== null;
}

export function isEmpty(value: any): boolean {
    return !isNotNull(value) || value === '';
}

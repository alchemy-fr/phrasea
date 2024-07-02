export function pushUnique<T>(array: T[], newValue: T): void {
    if (array.some(i => i === newValue)) {
        return;
    }

    array.push(newValue);
}

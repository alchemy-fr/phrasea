export type VoidFunction = () => void;

export const voidFunc: VoidFunction = () => {};

export function toArray<T>(object: Record<string, T>): T[] {
    return Object.keys(object).map(k => object[k]);
}

export function hasProp(object: object, key: string): boolean {
    return Object.prototype.hasOwnProperty.call(object, key);
}

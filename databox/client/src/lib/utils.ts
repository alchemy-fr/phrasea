export type VoidFunction = () => void;

export const voidFunc: VoidFunction = () => {};

export function toArray<T>(object: Record<string, T>): T[] {
    return Object.keys(object).map(k => object[k]);
}

export function hasProp<T extends object = object>(object: object, key: string): object is T {
    return Object.prototype.hasOwnProperty.call(object, key);
}

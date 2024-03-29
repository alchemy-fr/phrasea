export type VoidFunction = () => void;

export const voidFunc: VoidFunction = () => {};

export function toArray<T>(object: Record<string, T>): T[] {
    return Object.keys(object).map(k => object[k]);
}

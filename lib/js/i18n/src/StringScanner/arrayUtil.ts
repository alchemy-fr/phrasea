
export function removeElementsAtPositions<T = any>(positions: number[], arr: T[]): T[] {
    return arr.filter((_, i) => !positions.includes(i));
}

import {ToKeyFuncTypeScoped} from "../types";

export function normalizeList<T>(a: T[], toKey: ToKeyFuncTypeScoped<T>): string[] {
    return a
        .map(toKey)
        .sort((a, b) => a.localeCompare(b));
}

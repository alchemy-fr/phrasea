import {ToKeyFuncTypeScoped} from '../types';
import {normalizeList} from './normalize';

export function listsAreSame<T>(
    a: T[],
    b: T[],
    toKey: ToKeyFuncTypeScoped<T>
): boolean {
    if (a.length !== b.length) {
        return false;
    }

    const an = normalizeList<T>(a, toKey);
    const bn = normalizeList<T>(b, toKey);
    for (let i = 0; i < an.length; i++) {
        if (an[i] !== bn[i]) {
            return false;
        }
    }

    return true;
}

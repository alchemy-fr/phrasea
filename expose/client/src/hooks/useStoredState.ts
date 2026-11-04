'use client';

import {useCallback, useEffect, useState} from 'react';

/**
 * State remembered in localStorage, for the browser: the default until
 * read, and when the stored value is not valid any more.
 */
export function useStoredState<T extends string>(
    key: string,
    defaultValue: T,
    isValid: (value: string) => boolean
): [T, (value: T) => void] {
    const [value, setValue] = useState<T>(defaultValue);

    useEffect(() => {
        try {
            const stored = localStorage.getItem(key);
            if (stored !== null && isValid(stored)) {
                setValue(stored as T);
            }
        } catch {
            // storage unavailable
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);

    const set = useCallback(
        (next: T) => {
            setValue(next);
            try {
                localStorage.setItem(key, next);
            } catch {
                // storage unavailable
            }
        },
        [key]
    );

    return [value, set];
}

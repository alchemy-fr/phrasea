import {useLayoutEffect, useRef, useState} from 'react';

export function useDebounceLoader(value: boolean): boolean {
    const [debouncedValue, setDebouncedValue] = useState<boolean>(false);
    const timer = useRef<ReturnType<typeof setTimeout>>();

    useLayoutEffect(() => {
        clearTimeout(timer.current);
        if (value) {
            timer.current = setTimeout(() => setDebouncedValue(true), 50);
        } else {
            setDebouncedValue(false);
        }

        return () => {
            clearTimeout(timer.current);
        };
    }, [value]);

    return debouncedValue;
}

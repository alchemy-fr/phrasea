import {useRef} from 'react';

export function useDebounce() {
    const timer = useRef<ReturnType<typeof setTimeout>>();

    return (handler: () => any, delay?: number) => {
        clearTimeout(timer.current);

        timer.current = setTimeout(
            handler,
            delay,
        );
    }
}

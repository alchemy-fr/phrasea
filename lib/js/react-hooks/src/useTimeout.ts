import React from 'react';

export function useTimeout(
    handler: () => void | undefined,
    delay: number | undefined
) {
    const timeoutRef = React.useRef<ReturnType<typeof setTimeout>>();

    React.useEffect(() => {
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }

        if (undefined !== delay && handler) {
            timeoutRef.current = setTimeout(handler, delay);
        }

        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, [delay, handler]);

    return timeoutRef;
}

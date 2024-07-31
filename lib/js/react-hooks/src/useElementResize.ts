import React, {useRef} from 'react';

export function useElementResize(element: HTMLElement | null | undefined) {
    const timer = useRef<ReturnType<typeof setTimeout>>();
    const [size, setSize] = React.useState<{
        width: number;
        height: number;
    }>();

    React.useEffect(() => {
        if (!element) {
            return;
        }

        const resizeObserver = new ResizeObserver((entries) => {
            const entry = (entries[0])?.target as HTMLElement | undefined;
            if (entry) {
                if (timer.current) {
                    clearTimeout(timer.current);
                }
                timer.current = setTimeout(
                    () => {
                        setSize({
                            width: entry.clientWidth,
                            height: entry.clientHeight,
                        })
                    },
                    100,
                );
            }
        });

        resizeObserver.observe(element);
    }, [element, timer]);

    return size;
}

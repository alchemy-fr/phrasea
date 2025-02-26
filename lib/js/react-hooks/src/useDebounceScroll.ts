import React, {useRef} from 'react';

export function useDebounceScroll(
    container: HTMLElement | null | undefined,
    delay: number = 100
): number {
    const [scrollTop, setScrollTop] = React.useState(0);
    const timer = useRef<ReturnType<typeof setTimeout>>();

    React.useEffect(() => {
        clearTimeout(timer.current);

        if (container) {
            const listener = (e: Event) => {
                timer.current = setTimeout(
                    () =>
                        setScrollTop(p => {
                            const n = (e.target as HTMLElement).scrollTop;

                            if (Math.abs(p - n) > 100) {
                                return n;
                            }

                            return p;
                        }),
                    delay
                );
            };
            container.addEventListener('scroll', listener);

            return () => {
                clearTimeout(timer.current);
                container?.removeEventListener('scroll', listener);
            };
        }
    }, [container]);

    return scrollTop;
}

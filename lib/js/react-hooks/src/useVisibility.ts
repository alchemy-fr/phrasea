import {MutableRefObject, useEffect, useRef} from 'react';

export type IsVisibleCallback = (isVisible: boolean) => void;

type Props<E extends HTMLElement> = {
    shouldTrack: boolean;
    callback: IsVisibleCallback;
    containerRef?: MutableRefObject<E | null>;
};

export default function useVisibility<E extends HTMLElement>({
    shouldTrack,
    callback,
    containerRef,
}: Props<E>) {
    const elementRef = useRef<E | null>(null);
    const theRef = containerRef ?? elementRef;

    useEffect(() => {
        if (shouldTrack && theRef.current) {
            const options = {
                root: document.documentElement,
            };

            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    callback(entry.intersectionRatio > 0);
                });
            }, options);

            observer.observe(theRef.current!);

            return () => {
                observer.disconnect();
            };
        }
    }, [shouldTrack, theRef.current, callback]);

    return {
        elementRef: theRef,
    };
}

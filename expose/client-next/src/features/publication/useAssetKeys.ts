'use client';

import {useEffect, useRef} from 'react';

function isEditable(target: EventTarget | null): boolean {
    return (
        target instanceof HTMLElement &&
        (target.isContentEditable ||
            ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
    );
}

/**
 * ← / → move to the previous / next asset, Escape leaves. Ignored while
 * typing, and while a dialog (download terms, email…) is open.
 */
export function useAssetKeys(handlers: {
    onPrevious?: () => void;
    onNext?: () => void;
    onEscape?: () => void;
}): void {
    const ref = useRef(handlers);
    ref.current = handlers;

    useEffect(() => {
        const onKeyDown = (e: KeyboardEvent) => {
            if (
                e.defaultPrevented ||
                e.altKey ||
                e.ctrlKey ||
                e.metaKey ||
                isEditable(e.target) ||
                document.querySelector('[data-slot="dialog-content"]')
            ) {
                return;
            }
            const {onPrevious, onNext, onEscape} = ref.current;
            const handler =
                e.key === 'ArrowLeft'
                    ? onPrevious
                    : e.key === 'ArrowRight'
                      ? onNext
                      : e.key === 'Escape'
                        ? onEscape
                        : undefined;
            if (handler) {
                e.preventDefault();
                handler();
            }
        };
        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);
}

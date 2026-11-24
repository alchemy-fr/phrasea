'use client';

import {useEffect} from 'react';
import {Toaster as SonnerToaster, toast} from 'sonner';

const toastTextSelector = '[data-sonner-toast] [data-content]';

/**
 * Error toasts stay until the user closes them, so that an error is never
 * missed. Sonner only has a global duration: every `toast.error()` of the
 * clients gets an infinite one by default (an explicit duration still wins).
 */
const sonnerError = toast.error;
toast.error = (message, data) =>
    sonnerError(message, {duration: Infinity, ...data});

/**
 * Sonner's toast captures the pointer on pointerdown (swipe to dismiss),
 * which prevents selecting its text. Pointer events starting on the toast
 * text are stopped before they reach React, so the message can be selected
 * and copied (swiping still works from the rest of the toast).
 */
export function Toaster() {
    useEffect(() => {
        const onPointerDown = (e: PointerEvent) => {
            if (
                e.target instanceof Element &&
                e.target.closest(toastTextSelector)
            ) {
                e.stopPropagation();
            }
        };
        document.addEventListener('pointerdown', onPointerDown, true);

        return () =>
            document.removeEventListener('pointerdown', onPointerDown, true);
    }, []);

    return (
        <SonnerToaster
            position="bottom-left"
            richColors
            closeButton
            toastOptions={{
                duration: 5000,
                classNames: {content: 'cursor-text select-text'},
            }}
        />
    );
}

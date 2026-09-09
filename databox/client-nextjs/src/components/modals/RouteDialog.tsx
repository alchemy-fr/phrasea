'use client';

import {ReactNode, useCallback, useState} from 'react';
import {useRouter} from 'next/navigation';
import {Dialog, DialogContent, DialogSize} from '@/components/ui/dialog';
import {routes} from '@/lib/routes';

/**
 * Dialog bound to a route: closing it navigates back (or to the assets
 * screen when the viewer was opened from a direct link).
 */
export function RouteDialog({
    children,
    size = 'lg',
    className,
    hideClose,
    closeOnEscape = true,
    onClose,
}: {
    children: ReactNode;
    size?: DialogSize;
    className?: string;
    hideClose?: boolean;
    closeOnEscape?: boolean;
    onClose?: () => void;
}) {
    const router = useRouter();
    const [open, setOpen] = useState(true);

    const close = useCallback(() => {
        setOpen(false);
        onClose?.();
        // Give the exit animation a frame, then leave the route
        setTimeout(() => {
            if (
                window.history.length > 1 && document.referrer !== ''
                    ? true
                    : window.history.state?.idx > 0
            ) {
                router.back();
            } else {
                router.push(routes.assets());
            }
        }, 120);
    }, [router, onClose]);

    return (
        <Dialog open={open} onOpenChange={o => !o && close()}>
            <DialogContent
                size={size}
                className={className}
                hideClose={hideClose}
                onEscapeKeyDown={e => {
                    if (!closeOnEscape) {
                        e.preventDefault();
                    }
                }}
            >
                {children}
            </DialogContent>
        </Dialog>
    );
}

export function useCloseRoute() {
    const router = useRouter();

    return useCallback(() => {
        if (window.history.length > 1) {
            router.back();
        } else {
            router.push(routes.assets());
        }
    }, [router]);
}

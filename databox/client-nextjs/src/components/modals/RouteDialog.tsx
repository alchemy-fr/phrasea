'use client';

import {
    createContext,
    PropsWithChildren,
    ReactNode,
    RefObject,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import {usePathname, useRouter, useSearchParams} from 'next/navigation';
import {Dialog, DialogContent, DialogSize} from '@/components/ui/dialog';
import {modalExitDuration} from './ModalProvider';
import {routes} from '@/lib/routes';

/** URL of the screen the app is on, updated after every navigation. */
const LastUrl = createContext<RefObject<string | undefined> | null>(null);

/**
 * Where the dialog currently open sends the user when it closes. Held by the
 * dialog itself, which outlives the screens it contains: a tab page remounts
 * on every tab change, long after the URL it was opened from is reachable.
 */
const ReturnUrl = createContext<string | null>(null);

/**
 * Remembers the current URL so that a dialog bound to a route knows where to
 * send the user when it closes.
 *
 * Must wrap both the page and the `@modal` slot: the ref is written in an
 * effect, so a dialog mounting reads — during its own render, before that
 * effect runs — the URL of the screen it was opened from.
 */
export function RouteHistoryProvider({children}: PropsWithChildren) {
    const pathname = usePathname();
    const params = useSearchParams();
    const lastUrl = useRef<string | undefined>(undefined);
    const query = params.toString();

    useEffect(() => {
        lastUrl.current = query ? `${pathname}?${query}` : pathname;
    }, [pathname, query]);

    return <LastUrl.Provider value={lastUrl}>{children}</LastUrl.Provider>;
}

/**
 * Leaves the route a dialog is bound to, for the screen it was opened from —
 * as a push, not a back: the dialog stays in the history, so the browser back
 * button reopens it and the forward entries are left alone.
 *
 * The destination is frozen on the first render, before the dialog has had a
 * chance to become "the current URL", and so that mirroring the active tab in
 * the URL later on does not move it.
 */
export function useCloseRoute(): () => void {
    const router = useRouter();
    const openedFrom = useOpenedFrom();

    return useCallback(() => {
        // The screen underneath is still mounted: keep it where it was
        router.push(openedFrom, {scroll: false});
    }, [router, openedFrom]);
}

/**
 * The URL to return to: the one the enclosing dialog froze when it opened, or
 * — for screens that are not wrapped in a `RouteDialog` — the URL that was
 * current when this component first rendered.
 */
function useOpenedFrom(): string {
    const lastUrl = useContext(LastUrl);
    const fromDialog = useContext(ReturnUrl);
    const [ownFallback] = useState(() => lastUrl?.current ?? routes.assets());

    return fromDialog ?? ownFallback;
}

/**
 * Dialog bound to a route: closing it leaves the route, once the exit
 * animation had a chance to play.
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
    const returnUrl = useOpenedFrom();
    const closeRoute = useCloseRoute();
    const [open, setOpen] = useState(true);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

    // Leaving through the browser back button unmounts us mid-animation:
    // the pending navigation would then fire a second one.
    useEffect(() => () => clearTimeout(timer.current), []);

    const close = useCallback(() => {
        setOpen(false);
        onClose?.();
        timer.current = setTimeout(closeRoute, modalExitDuration);
    }, [closeRoute, onClose]);

    return (
        <Dialog open={open} onOpenChange={o => !o && close()}>
            <DialogContent
                data-testid="route-dialog"
                size={size}
                className={className}
                hideClose={hideClose}
                onEscapeKeyDown={e => {
                    if (!closeOnEscape) {
                        e.preventDefault();
                    }
                }}
            >
                <ReturnUrl.Provider value={returnUrl}>
                    {children}
                </ReturnUrl.Provider>
            </DialogContent>
        </Dialog>
    );
}

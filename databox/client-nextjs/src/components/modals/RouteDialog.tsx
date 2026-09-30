'use client';

import {
    createContext,
    PropsWithChildren,
    ReactNode,
    RefObject,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import {usePathname, useRouter, useSearchParams} from 'next/navigation';
import {Dialog, DialogContent, DialogSize} from '@/components/ui/dialog';
import {modalExitDuration} from './ModalProvider';
import {pathOf, RouteOrigins} from './routeOrigins';
import {routes} from '@/lib/routes';
import {
    askDiscardChanges,
    hasUnsavedChanges,
    UnsavedChangesScope,
    useUnsavedChangesChildScope,
} from '@/lib/navigation/unsavedChanges';

type RouteHistory = {
    /** URL of the screen the app is on, updated after every navigation */
    lastUrl: RefObject<string | undefined>;
    origins: RouteOrigins;
};

const RouteHistoryContext = createContext<RouteHistory | null>(null);

/**
 * Where the dialog currently open sends the user when it closes. Held by the
 * dialog itself, which outlives the screens it contains: a tab page remounts
 * on every tab change.
 */
const ReturnUrl = createContext<string | null>(null);

/** URL of the screen the enclosing `RouteDialog` was opened from */
export function useRouteDialogOrigin(): string {
    return useContext(ReturnUrl) ?? routes.assets();
}

/** Closes the enclosing `RouteDialog`, see `useCloseRouteDialog` */
const CloseDialog = createContext<((url?: string) => void) | null>(null);

/**
 * Closes the enclosing `RouteDialog` (with its exit animation) for `url`, or
 * for where it closes to by default. The screen at `url` takes over the
 * dialog's origin: closing it leads where the dialog would have.
 */
export function useCloseRouteDialog(): (url?: string) => void {
    const close = useContext(CloseDialog);
    if (!close) {
        throw new Error('useCloseRouteDialog must be used in a RouteDialog');
    }

    return close;
}

/**
 * Tracks the navigation so that a screen bound to a route knows where to send
 * the user when it closes.
 *
 * Must wrap both the page and the `@modal` slot: `lastUrl` is written in an
 * effect, so a screen mounting reads — during its own render, before that
 * effect runs — the URL displayed before it.
 */
export function RouteHistoryProvider({children}: PropsWithChildren) {
    const pathname = usePathname();
    const params = useSearchParams();
    const lastUrl = useRef<string | undefined>(undefined);
    const [origins] = useState(() => new RouteOrigins());
    const history = useMemo(() => ({lastUrl, origins}), [origins]);
    const query = params.toString();

    useEffect(() => {
        lastUrl.current = query ? `${pathname}?${query}` : pathname;
    }, [pathname, query]);

    return (
        <RouteHistoryContext.Provider value={history}>
            {children}
        </RouteHistoryContext.Provider>
    );
}

/**
 * Origin of the route screen identified by `screen` (null: not a screen, do
 * nothing), resolved once when it mounts.
 */
function useScreenOrigin(
    screen: string | null,
    pathname: string | null
): string {
    const history = useContext(RouteHistoryContext);
    const [origin] = useState(() =>
        screen !== null && history
            ? history.origins.resolve(screen, history.lastUrl.current)
            : routes.assets()
    );

    useEffect(() => {
        if (screen !== null && pathname !== null) {
            history?.origins.register(pathname, screen);
        }
    }, [history, pathname, screen]);

    return origin;
}

/**
 * Leaves the route a screen is bound to, for the screen it was opened from —
 * as a push, not a back: it stays in the history, so the browser back button
 * reopens it and the forward entries are left alone.
 *
 * `screen` is the URL prefix shared by every URL of that screen (like
 * `RouteDialog`'s `routeKey`). A viewer that navigates between its own URLs —
 * the basket view switching basket — must pass it, otherwise each of them
 * counts as a new screen and closing walks back through them one by one
 * instead of leaving for the page it was opened from.
 */
export function useCloseRoute(screen?: string): () => void {
    const router = useRouter();
    const openedFrom = useOpenedFrom(screen);
    // Inside a dialog: only its own forms are lost
    const scope = useContext(UnsavedChangesScope);

    return useCallback(
        () =>
            whenLeaving(scope, () => {
                // The screen underneath is still mounted: keep it where it was
                router.push(openedFrom, {scroll: false});
            }),
        [router, openedFrom, scope]
    );
}

/**
 * The enclosing dialog's origin, or — for route screens that are not wrapped
 * in a `RouteDialog` (viewers) — the origin of the screen at the URL this
 * component mounted on.
 */
function useOpenedFrom(screen?: string): string {
    const fromDialog = useContext(ReturnUrl);
    // Not `window.location`: on a client-side navigation, the screen renders
    // before the router writes its URL to the history — it would read the URL
    // of the screen it was opened from, and take it for its own. Only the URL
    // the screen mounted on is kept.
    const pathname = usePathname();
    const [mountPath] = useState(() => (fromDialog === null ? pathname : null));
    const own = useScreenOrigin(
        mountPath === null ? null : (screen ?? mountPath),
        mountPath
    );

    return fromDialog ?? own;
}

/**
 * Dialog bound to a route: closing it leaves the route, once the exit
 * animation had a chance to play.
 */
export function RouteDialog({
    children,
    routeKey,
    size = 'lg',
    className,
    hideClose,
    closeOnEscape = true,
    onClose,
    closeTo,
}: {
    children: ReactNode;
    /**
     * URL prefix shared by all the URLs of this dialog (e.g. without the tab).
     * Defaults to the URL it opened on.
     */
    routeKey?: string;
    size?: DialogSize;
    className?: string;
    hideClose?: boolean;
    closeOnEscape?: boolean;
    onClose?: () => void;
    /**
     * Where closing leads, given the screen the dialog was opened from
     * (default: that screen).
     */
    closeTo?: (origin: string) => string;
}) {
    const router = useRouter();
    const pathname = usePathname();
    const history = useContext(RouteHistoryContext);
    const [screen] = useState(() => routeKey ?? pathname);
    const returnUrl = useScreenOrigin(screen, pathname);
    const [open, setOpen] = useState(true);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const scope = useUnsavedChangesChildScope();

    // Leaving through the browser back button unmounts us mid-animation:
    // the pending navigation would then fire a second one.
    useEffect(() => () => clearTimeout(timer.current), []);

    const close = useCallback(
        (target?: string) =>
            whenLeaving(scope, () => {
                const url = target ?? closeTo?.(returnUrl) ?? returnUrl;
                if (pathOf(url) !== pathOf(returnUrl)) {
                    history?.origins.handOver(
                        pathOf(url),
                        window.location.pathname,
                        returnUrl
                    );
                }
                setOpen(false);
                onClose?.();
                timer.current = setTimeout(
                    () => router.push(url, {scroll: false}),
                    modalExitDuration
                );
            }),
        [router, returnUrl, onClose, closeTo, history, scope]
    );

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
                    <CloseDialog.Provider value={close}>
                        <UnsavedChangesScope.Provider value={scope}>
                            {children}
                        </UnsavedChangesScope.Provider>
                    </CloseDialog.Provider>
                </ReturnUrl.Provider>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Runs `leave` once the forms of `scope` may be dropped: right away when none
 * is dirty (synchronously), after the user confirmed otherwise.
 */
function whenLeaving(scope: string, leave: () => void): void {
    if (!hasUnsavedChanges(scope)) {
        leave();

        return;
    }
    void askDiscardChanges().then(discard => discard && leave());
}

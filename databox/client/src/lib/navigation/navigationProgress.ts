/**
 * Start of the App Router navigations, reported by `onRouterTransitionStart`
 * (see `src/instrumentation-client.ts`) to `NavigationProgress`.
 *
 * Next has no matching "end" hook: the navigation is over once the target
 * URL is committed (`usePathname` / `useSearchParams`).
 */

type Listener = (url: URL) => void;

const listeners = new Set<Listener>();

export function notifyNavigationStart(href: string): void {
    const url = new URL(href, window.location.href);
    listeners.forEach(listener => listener(url));
}

export function onNavigationStart(listener: Listener): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

/** What a navigation must change to render another screen: not the hash. */
export function navigationKey(pathname: string, search: string): string {
    const query = new URLSearchParams(search).toString();

    return query ? `${pathname}?${query}` : pathname;
}

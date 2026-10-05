'use client';

import {createContext, ReactNode, useContext, useMemo, useState} from 'react';

export type RoutePathNavigate = (
    segments: string[],
    options?: {replace?: boolean}
) => void;

export type RoutePath = {
    /** Segments of the URL below the screen (e.g. `[itemId]`) */
    segments: string[];
    /** Changes them, with a history entry unless `replace` */
    navigate: RoutePathNavigate;
};

const RoutePathContext = createContext<RoutePath | null>(null);

/**
 * Part of the URL owned by the screen below, to mirror what it shows (the item
 * edited in a list, a nested panel...) so that a refresh or a shared link
 * lands on it: `/workspaces/:id/manage/tags/:tagId`.
 *
 * Provided by `TabbedRouteDialogShell` for each tab, or by a page with
 * `UrlPathProvider`. Without a provider, the path is local state: the screen
 * works the same, the URL just does not follow.
 */
export function useRoutePath(): RoutePath {
    const context = useContext(RoutePathContext);
    const [local, setLocal] = useState<string[]>([]);

    return (
        context ?? {segments: local, navigate: segments => setLocal(segments)}
    );
}

export function RoutePathProvider({
    value,
    children,
}: {
    value: RoutePath;
    children: ReactNode;
}) {
    return (
        <RoutePathContext.Provider value={value}>
            {children}
        </RoutePathContext.Provider>
    );
}

/**
 * Hands the segments below `prefix` to a nested screen (e.g. the values of the
 * entity list selected: `[listId, 'manage']`). Renders `children` with an
 * empty path when the current one is not under `prefix`.
 */
export function NestedRoutePath({
    prefix,
    children,
}: {
    prefix: string[];
    children: ReactNode;
}) {
    const {segments, navigate} = useRoutePath();
    const key = prefix.join('/');
    const under =
        segments.length >= prefix.length &&
        prefix.every((s, i) => segments[i] === s);
    const rest = under ? segments.slice(prefix.length).join('/') : '';
    const value = useMemo<RoutePath>(
        () => ({
            segments: rest ? rest.split('/') : [],
            navigate: (sub, options) =>
                navigate([...key.split('/'), ...sub], options),
        }),
        [rest, key, navigate]
    );

    return <RoutePathProvider value={value}>{children}</RoutePathProvider>;
}

/** URL `base` + the segments: `/base/a/b` */
export function joinRoutePath(base: string, segments: string[]): string {
    return [base.replace(/\/$/, ''), ...segments.map(encodeURIComponent)].join(
        '/'
    );
}

/** The segments of `pathname` below `base`, or `null` when not under it */
export function splitRoutePath(
    pathname: string,
    base: string
): string[] | null {
    const root = base.replace(/\/$/, '');
    if (pathname !== root && !pathname.startsWith(`${root}/`)) {
        return null;
    }

    return pathname
        .slice(root.length)
        .split('/')
        .filter(Boolean)
        .map(decodeURIComponent);
}

/**
 * Maps the path of a whole page (a route with an optional catch-all below
 * `base`) to `useRoutePath`. Changes go through `history.pushState`: Next
 * syncs `usePathname` with it, without a navigation.
 */
export function UrlPathProvider({
    base,
    pathname,
    children,
}: {
    base: string;
    pathname: string;
    children: ReactNode;
}) {
    const joined = (splitRoutePath(pathname, base) ?? []).join('/');
    const value = useMemo<RoutePath>(
        () => ({
            segments: joined ? joined.split('/') : [],
            navigate: (segments, options) =>
                pushPath(joinRoutePath(base, segments), options),
        }),
        [joined, base]
    );

    return <RoutePathProvider value={value}>{children}</RoutePathProvider>;
}

/**
 * `null` state, not the current one: Next only syncs its router with the
 * history calls that do not carry its own internal state.
 */
export function pushPath(url: string, {replace}: {replace?: boolean} = {}) {
    if (url === window.location.pathname) {
        return;
    }
    if (replace) {
        window.history.replaceState(null, '', url);
    } else {
        window.history.pushState(null, '', url);
    }
}

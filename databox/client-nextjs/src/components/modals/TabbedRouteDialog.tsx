'use client';

import {
    ComponentType,
    memo,
    ReactNode,
    useContext,
    useMemo,
    useState,
} from 'react';
import {usePathname} from 'next/navigation';
import {RouteDialog, useCloseRoute} from './RouteDialog';
import {
    DialogBody,
    DialogHeader,
    DialogTitle,
    DialogSize,
} from '@/components/ui/dialog';
import {Tabs, SideTabsList, SideTabsTrigger} from '@/components/ui/misc';
import {
    askDiscardChanges,
    hasUnsavedChanges,
    UnsavedChangesScope,
    useUnsavedChangesStore,
} from '@/lib/navigation/unsavedChanges';
import {
    joinRoutePath,
    pushPath,
    RoutePath,
    RoutePathProvider,
    splitRoutePath,
} from '@/lib/navigation/routePath';

export type DialogTabProps = {
    /** Close the whole dialog */
    onClose: () => void;
};

export type DialogTab<P extends object> = {
    id: string;
    title: ReactNode;
    icon?: ReactNode;
    component: ComponentType<P & DialogTabProps>;
    props?: Partial<P>;
    enabled?: boolean;
};

/**
 * Dialog whose tabs are mirrored in the URL (`…/manage/:tab`), listed as a
 * menu on the left: a management dialog has many of them, and a vertical
 * list reads them on one line each whatever the language.
 *
 * It belongs in the `layout.tsx` of the `manage` segment and renders the tabs
 * itself; `[tab]/page.tsx` only exists so that every tab URL resolves (direct
 * link, interception) and renders nothing.
 *
 * Switching tabs is a native `history.pushState`: Next syncs `usePathname`
 * with it, so the tab lands in the history and the back button walks through
 * the tabs — without a navigation, hence without a server round trip, and
 * without remounting anything. Visited tabs stay mounted (hidden), so coming
 * back to one is instant and keeps its state (scroll).
 *
 * Each tab is its own unsaved-changes scope: leaving a tab holding unsaved
 * changes asks first, and a discarded tab is remounted (its edits dropped).
 *
 * The URL below the tab (`…/manage/:tab/:item…`) belongs to the tab
 * (`useRoutePath`): it mirrors what the tab shows, e.g. the item edited.
 * Each tab keeps its own, restored when coming back to it. The route segment
 * is then `[tab]/[[...path]]/page.tsx`.
 */
export function TabbedRouteDialogShell<P extends object>({
    title,
    subtitle,
    tabs,
    baseProps,
    buildTabHref,
    size = 'lg',
    placeholder,
}: {
    title: ReactNode;
    subtitle?: ReactNode;
    tabs: DialogTab<P>[];
    /** Props given to every tab; the tabs are not rendered while undefined */
    baseProps: P | undefined;
    buildTabHref: (tab: string) => string;
    size?: DialogSize;
    /** Shown instead of the tabs while the entity loads, or when it is missing */
    placeholder?: ReactNode;
}) {
    const pathname = usePathname();
    // Every tab URL is `<routeKey>/<tab>`: the dialog's identity
    const [routeKey] = useState(() => buildTabHref('').replace(/\/$/, ''));
    const enabledTabs = tabs.filter(t => t.enabled !== false);
    const [urlTab, ...urlPath] = splitRoutePath(pathname, routeKey) ?? [];
    const active =
        enabledTabs.find(t => t.id === urlTab)?.id ?? enabledTabs[0]?.id;

    return (
        <RouteDialog routeKey={routeKey} size={size} className="h-[85dvh]">
            <DialogHeader>
                <DialogTitle className="pr-6">{title}</DialogTitle>
                {subtitle ? (
                    <div className="text-xs text-muted-foreground">
                        {subtitle}
                    </div>
                ) : null}
            </DialogHeader>
            {placeholder || !baseProps || !active ? (
                <DialogBody className="flex items-center justify-center">
                    {placeholder}
                </DialogBody>
            ) : (
                <DialogTabs
                    tabs={enabledTabs}
                    active={active}
                    activePath={active === urlTab ? urlPath : []}
                    baseProps={baseProps}
                    buildTabHref={buildTabHref}
                />
            )}
        </RouteDialog>
    );
}

/** Rendered inside the `RouteDialog`, to nest the tab scopes in its own */
function DialogTabs<P extends object>({
    tabs,
    active,
    activePath,
    baseProps,
    buildTabHref,
}: {
    tabs: DialogTab<P>[];
    active: string;
    /** URL segments below the active tab */
    activePath: string[];
    baseProps: P;
    buildTabHref: (tab: string) => string;
}) {
    const dialogScope = useContext(UnsavedChangesScope);
    // Outside the tab scopes: closing drops the forms of every tab
    const closeRoute = useCloseRoute();
    const tabScope = (tab: string) => `${dialogScope}/tab-${tab}`;

    const [visited, setVisited] = useState<string[]>([]);
    const [shown, setShown] = useState(active);
    if (shown !== active) {
        setShown(active);
        // Left with unsaved changes: the user accepted to discard them
        // (here, or through the back button guard). Remount it next time.
        const dirtyScopes = Object.values(
            useUnsavedChangesStore.getState().dirty
        );
        const left = tabScope(shown);
        if (dirtyScopes.some(s => s === left || s.startsWith(`${left}/`))) {
            setVisited(visited.filter(v => v !== shown));
        }
    }
    if (!visited.includes(active)) {
        setVisited(v => (v.includes(active) ? v : [...v, active]));
    }
    // Path of each tab: the URL's for the active one, the last one it had
    // for the others (restored when switching back)
    const [paths, setPaths] = useState<Record<string, string>>({});
    const activeKey = activePath.join('/');
    if ((paths[active] ?? '') !== activeKey) {
        setPaths({...paths, [active]: activeKey});
    }
    const tabHref = (tab: string, path = paths[tab] ?? '') =>
        joinRoutePath(buildTabHref(tab), path ? path.split('/') : []);

    const selectTab = (next: string) => {
        if (next === active) {
            return;
        }
        const go = () => pushPath(tabHref(next));
        if (!hasUnsavedChanges(tabScope(active))) {
            go();

            return;
        }
        void askDiscardChanges().then(discard => discard && go());
    };

    return (
        <Tabs
            value={active}
            onValueChange={selectTab}
            orientation="vertical"
            className="-mx-6 flex min-h-0 flex-1 border-t px-6"
        >
            <SideTabsList>
                {tabs.map(tab => (
                    <SideTabsTrigger key={tab.id} value={tab.id}>
                        {tab.icon}
                        <span className="min-w-0 truncate">{tab.title}</span>
                    </SideTabsTrigger>
                ))}
            </SideTabsList>
            <DialogBody className="mx-0 pt-4 pr-0 pl-4">
                {tabs
                    .filter(tab => visited.includes(tab.id))
                    .map(tab => (
                        <div
                            key={tab.id}
                            hidden={tab.id !== active}
                            data-testid={`dialog-tab-${tab.id}`}
                        >
                            <UnsavedChangesScope.Provider
                                value={tabScope(tab.id)}
                            >
                                <TabPath
                                    path={paths[tab.id] ?? ''}
                                    href={path => tabHref(tab.id, path)}
                                >
                                    <TabContent
                                        component={tab.component}
                                        baseProps={baseProps}
                                        tabProps={tab.props}
                                        onClose={closeRoute}
                                    />
                                </TabPath>
                            </UnsavedChangesScope.Provider>
                        </div>
                    ))}
            </DialogBody>
        </Tabs>
    );
}

/**
 * The tab's `useRoutePath`. Stable while the tab's own path does not change,
 * so that switching tabs does not re-render the others.
 */
function TabPath({
    path,
    href,
    children,
}: {
    path: string;
    href: (path: string) => string;
    children: ReactNode;
}) {
    // The dialog's URLs do not change while it is open
    const [hrefOf] = useState(() => href);
    const value = useMemo<RoutePath>(
        () => ({
            segments: path ? path.split('/') : [],
            navigate: (segments, options) =>
                pushPath(hrefOf(segments.join('/')), options),
        }),
        [path, hrefOf]
    );

    return <RoutePathProvider value={value}>{children}</RoutePathProvider>;
}

type TabContentProps<P extends object> = {
    component: ComponentType<P & DialogTabProps>;
    baseProps: P;
    tabProps?: Partial<P>;
    onClose: () => void;
};

/**
 * Memoized: visited tabs stay mounted, and must not all re-render whenever the
 * shell does (every tab switch). Give the shell a stable `baseProps`
 * (`useMemo`) and module-level tab components — an inline component is a new
 * type on every render, which remounts the tab.
 */
const TabContent = memo(function TabContent<P extends object>({
    component: Component,
    baseProps,
    tabProps,
    onClose,
}: TabContentProps<P>) {
    return <Component {...baseProps} {...(tabProps ?? {})} onClose={onClose} />;
}) as <P extends object>(props: TabContentProps<P>) => ReactNode;

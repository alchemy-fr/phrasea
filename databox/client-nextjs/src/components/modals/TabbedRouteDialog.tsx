'use client';

import {ComponentType, memo, ReactNode, useState} from 'react';
import {usePathname} from 'next/navigation';
import {RouteDialog, useCloseRoute} from './RouteDialog';
import {
    DialogBody,
    DialogHeader,
    DialogTitle,
    DialogSize,
} from '@/components/ui/dialog';
import {Tabs, SideTabsList, SideTabsTrigger} from '@/components/ui/misc';

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
 * back to one is instant and keeps its state (scroll, unsaved edits).
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
    const urlTab = pathname.startsWith(`${routeKey}/`)
        ? pathname.slice(routeKey.length + 1).split('/')[0]
        : undefined;
    const active =
        enabledTabs.find(t => t.id === urlTab)?.id ?? enabledTabs[0]?.id;

    const [visited, setVisited] = useState<string[]>([]);
    if (active && !visited.includes(active)) {
        setVisited([...visited, active]);
    }

    const selectTab = (next: string) => {
        if (next !== active) {
            // `null`, not the current state: Next only syncs its router with
            // pushState calls that do not carry its own internal state
            window.history.pushState(null, '', buildTabHref(next));
        }
    };

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
            {placeholder || !baseProps ? (
                <DialogBody className="flex items-center justify-center">
                    {placeholder}
                </DialogBody>
            ) : (
                <Tabs
                    value={active}
                    onValueChange={selectTab}
                    orientation="vertical"
                    className="-mx-6 flex min-h-0 flex-1 border-t px-6"
                >
                    <SideTabsList>
                        {enabledTabs.map(tab => (
                            <SideTabsTrigger key={tab.id} value={tab.id}>
                                {tab.icon}
                                <span className="min-w-0 truncate">
                                    {tab.title}
                                </span>
                            </SideTabsTrigger>
                        ))}
                    </SideTabsList>
                    <DialogBody className="mx-0 pt-4 pr-0 pl-4">
                        {enabledTabs
                            .filter(tab => visited.includes(tab.id))
                            .map(tab => (
                                <div
                                    key={tab.id}
                                    hidden={tab.id !== active}
                                    data-testid={`dialog-tab-${tab.id}`}
                                >
                                    <TabContent
                                        component={tab.component}
                                        baseProps={baseProps}
                                        tabProps={tab.props}
                                    />
                                </div>
                            ))}
                    </DialogBody>
                </Tabs>
            )}
        </RouteDialog>
    );
}

type TabContentProps<P extends object> = {
    component: ComponentType<P & DialogTabProps>;
    baseProps: P;
    tabProps?: Partial<P>;
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
}: TabContentProps<P>) {
    // Inside the dialog: closes to the dialog's origin
    const closeRoute = useCloseRoute();

    return (
        <Component {...baseProps} {...(tabProps ?? {})} onClose={closeRoute} />
    );
}) as <P extends object>(props: TabContentProps<P>) => ReactNode;

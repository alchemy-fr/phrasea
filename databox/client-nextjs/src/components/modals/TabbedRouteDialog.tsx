'use client';

import {ComponentType, ReactNode, useState} from 'react';
import {
    usePathname,
    useRouter,
    useSelectedLayoutSegment,
} from 'next/navigation';
import {RouteDialog, useCloseRoute} from './RouteDialog';
import {
    DialogBody,
    DialogHeader,
    DialogTitle,
    DialogSize,
} from '@/components/ui/dialog';
import {
    Tabs,
    UnderlineTabsList,
    UnderlineTabsTrigger,
} from '@/components/ui/misc';

export type DialogTabProps = {
    /** Close the whole dialog */
    onClose: () => void;
};

/** What the shell needs to draw a tab — no content, that is the page's job. */
export type DialogTabHeader = {
    id: string;
    title: ReactNode;
    icon?: ReactNode;
    enabled?: boolean;
};

export type DialogTab<P extends object> = DialogTabHeader & {
    component: ComponentType<P & DialogTabProps>;
    props?: Partial<P>;
};

/**
 * Shell of a dialog whose tabs are route segments (`…/manage/:tab`).
 *
 * It belongs in the `layout.tsx` of the `manage` segment, with the tab content
 * in `[tab]/page.tsx`: a Next layout survives the navigation to a sibling
 * segment, so switching tabs is a real navigation — it lands in the history
 * and the back button walks through the tabs — while the dialog itself stays
 * mounted and never replays its opening.
 */
export function TabbedRouteDialogShell({
    title,
    subtitle,
    tabs,
    buildTabHref,
    size = 'lg',
    placeholder,
    children,
}: {
    title: ReactNode;
    subtitle?: ReactNode;
    tabs: DialogTabHeader[];
    buildTabHref: (tab: string) => string;
    size?: DialogSize;
    /** Shown instead of the tabs while the entity loads, or when it is missing */
    placeholder?: ReactNode;
    children?: ReactNode;
}) {
    const router = useRouter();
    const pathname = usePathname();
    // The tab is the segment right below this layout
    const segment = useSelectedLayoutSegment();
    // All the tabs are the same screen: identify it by the URL without the tab
    const [routeKey] = useState(() =>
        segment && pathname.endsWith(`/${segment}`)
            ? pathname.slice(0, -segment.length - 1)
            : pathname
    );
    const enabledTabs = tabs.filter(t => t.enabled !== false);
    const active =
        enabledTabs.find(t => t.id === segment)?.id ?? enabledTabs[0]?.id;

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
            {placeholder ? (
                <DialogBody className="flex items-center justify-center">
                    {placeholder}
                </DialogBody>
            ) : (
                <>
                    <Tabs
                        value={active}
                        onValueChange={next =>
                            router.push(buildTabHref(next), {scroll: false})
                        }
                    >
                        <UnderlineTabsList>
                            {enabledTabs.map(tab => (
                                <UnderlineTabsTrigger
                                    key={tab.id}
                                    value={tab.id}
                                >
                                    {tab.icon} {tab.title}
                                </UnderlineTabsTrigger>
                            ))}
                        </UnderlineTabsList>
                    </Tabs>
                    <DialogBody className="pt-4">{children}</DialogBody>
                </>
            )}
        </RouteDialog>
    );
}

/**
 * Content of the active tab, rendered by `[tab]/page.tsx` inside the shell.
 */
export function DialogTabContent<P extends object>({
    tabs,
    tab,
    baseProps,
}: {
    tabs: DialogTab<P>[];
    tab: string;
    baseProps: P | undefined;
}) {
    const closeRoute = useCloseRoute();
    const enabledTabs = tabs.filter(t => t.enabled !== false);
    const current = enabledTabs.find(t => t.id === tab) ?? enabledTabs[0];
    const Component = current?.component;

    if (!Component || !baseProps) {
        return null;
    }

    return (
        <Component
            {...baseProps}
            {...(current.props ?? {})}
            onClose={closeRoute}
        />
    );
}

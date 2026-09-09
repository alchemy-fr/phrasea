'use client';

import {ComponentType, ReactNode, useMemo} from 'react';
import {useRouter} from 'next/navigation';
import {RouteDialog} from './RouteDialog';
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

export type DialogTab<P extends object> = {
    id: string;
    title: ReactNode;
    icon?: ReactNode;
    component: ComponentType<P & DialogTabProps>;
    props?: Partial<P>;
    enabled?: boolean;
};

export type DialogTabProps = {
    /** Close the whole dialog */
    onClose: () => void;
};

/**
 * Dialog with tabs mirrored in the URL (`…/manage/:tab`).
 */
export function TabbedRouteDialog<P extends object>({
    title,
    tabs,
    activeTab,
    buildTabHref,
    size = 'lg',
    baseProps,
    subtitle,
}: {
    title: ReactNode;
    subtitle?: ReactNode;
    tabs: DialogTab<P>[];
    activeTab: string;
    buildTabHref: (tab: string) => string;
    size?: DialogSize;
    baseProps: P;
}) {
    const router = useRouter();
    const enabledTabs = useMemo(
        () => tabs.filter(t => t.enabled !== false),
        [tabs]
    );
    const current = enabledTabs.find(t => t.id === activeTab) ?? enabledTabs[0];
    const Component = current?.component;

    return (
        <RouteDialog size={size} className="h-[85dvh]">
            <DialogHeader>
                <DialogTitle className="pr-6">{title}</DialogTitle>
                {subtitle ? (
                    <div className="text-xs text-muted-foreground">
                        {subtitle}
                    </div>
                ) : null}
            </DialogHeader>
            <Tabs
                value={current?.id}
                onValueChange={tab =>
                    router.replace(buildTabHref(tab), {scroll: false})
                }
            >
                <UnderlineTabsList>
                    {enabledTabs.map(tab => (
                        <UnderlineTabsTrigger key={tab.id} value={tab.id}>
                            {tab.icon} {tab.title}
                        </UnderlineTabsTrigger>
                    ))}
                </UnderlineTabsList>
            </Tabs>
            <DialogBody className="pt-4">
                {Component ? (
                    <Component
                        key={current.id}
                        {...(baseProps as P)}
                        {...(current.props ?? {})}
                        onClose={() => router.back()}
                    />
                ) : null}
            </DialogBody>
        </RouteDialog>
    );
}

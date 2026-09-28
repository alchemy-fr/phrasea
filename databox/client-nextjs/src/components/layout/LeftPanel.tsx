'use client';

import {
    ComponentProps,
    PropsWithChildren,
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import {
    FolderTreeIcon,
    ShoppingBasketIcon,
    SlidersHorizontalIcon,
} from 'lucide-react';
import {useDndContext, useDndMonitor} from '@dnd-kit/core';
import {Tabs, TabsContent, TabsList, TabsTrigger} from '@/components/ui/misc';
import {useLayoutStore, LeftPanelTab} from './layoutStore';
import {useAuth} from '@/lib/auth/AuthProvider';
import {FacetsPanel} from '@/features/search/facets/FacetsPanel';
import {CollectionsPanel} from '@/features/collections/tree/CollectionsPanel';
import {BasketsPanel} from '@/features/baskets/BasketsPanel';
import {SavedSearchList} from '@/features/saved-searches/SavedSearchList';
import {PinnedStoriesPanel} from '@/features/stories/PinnedStoriesPanel';
import {useOptionalSearch} from '@/features/search/SearchProvider';
import {useDropTarget} from '@/features/dnd/useDropTarget';
import type {DragSource} from '@/features/dnd/types';

export function LeftPanel() {
    const {t} = useTranslation();
    const {isAuthenticated} = useAuth();
    const tab = useLayoutStore(s => s.leftPanelTab);
    const setTab = useLayoutStore(s => s.setLeftPanelTab);
    const search = useOptionalSearch();
    // The tab shown during a drag: the drop targets live in the tree and
    // the baskets, not in the facets. Kept once something was dropped.
    const [dragTab, setDragTab] = useState<LeftPanelTab>();

    useDndMonitor({
        onDragStart: e => {
            const source = e.active.data.current as DragSource | undefined;
            if (tab === 'facets' || source?.kind === 'collection-source') {
                setDragTab('tree');
            }
        },
        onDragEnd: e => {
            if (dragTab && e.over) {
                setTab(dragTab);
            }
            setDragTab(undefined);
        },
        onDragCancel: () => setDragTab(undefined),
    });

    return (
        <Tabs
            value={dragTab ?? tab}
            onValueChange={v => setTab(v as LeftPanelTab)}
            className="flex h-full min-h-0 flex-col"
            data-testid="left-panel"
        >
            <TabsList className="m-2 flex w-auto">
                <TabsTrigger
                    value="facets"
                    className="flex-auto px-2"
                    aria-label={t('panel.facets', 'Facets')}
                >
                    <SlidersHorizontalIcon />
                    <span className="sr-only lg:not-sr-only">
                        {t('panel.facets', 'Facets')}
                    </span>
                </TabsTrigger>
                <DroppableTabsTrigger
                    tab="tree"
                    onHover={setDragTab}
                    className="flex-auto px-2"
                    aria-label={t('panel.tree', 'Navigation')}
                >
                    <FolderTreeIcon />
                    <span className="sr-only lg:not-sr-only">
                        {t('panel.tree', 'Browse')}
                    </span>
                </DroppableTabsTrigger>
                {isAuthenticated ? (
                    <DroppableTabsTrigger
                        tab="baskets"
                        onHover={setDragTab}
                        className="flex-auto px-2"
                        aria-label={t('panel.baskets', 'Baskets')}
                    >
                        <ShoppingBasketIcon />
                        <span className="sr-only lg:not-sr-only">
                            {t('panel.baskets', 'Baskets')}
                        </span>
                    </DroppableTabsTrigger>
                ) : null}
            </TabsList>
            <TabsContent
                value="facets"
                className="min-h-0 flex-1 overflow-y-auto"
            >
                {search ? <FacetsPanel /> : null}
            </TabsContent>
            <DroppablePanel value="tree">
                <CollectionsPanel />
                {isAuthenticated ? (
                    <>
                        <PinnedStoriesPanel />
                        <SavedSearchList />
                    </>
                ) : null}
            </DroppablePanel>
            {isAuthenticated ? (
                <DroppablePanel value="baskets">
                    <BasketsPanel />
                </DroppablePanel>
            ) : null}
        </Tabs>
    );
}

/** A tab trigger that switches to its tab when hovered during a drag */
function DroppableTabsTrigger({
    tab,
    onHover,
    ...props
}: Omit<ComponentProps<typeof TabsTrigger>, 'value'> & {
    tab: LeftPanelTab;
    onHover: (tab: LeftPanelTab) => void;
}) {
    const drop = useDropTarget({type: 'tab', tab});

    useEffect(() => {
        if (!drop.isOver) {
            return;
        }
        const timer = setTimeout(() => onHover(tab), 300);

        return () => clearTimeout(timer);
    }, [drop.isOver, tab, onHover]);

    return <TabsTrigger ref={drop.setNodeRef} value={tab} {...props} />;
}

/**
 * The scrollable content of a tab, droppable as a whole so that auto-scroll
 * keeps going between rows. Sticky rows (workspaces) drift from where
 * dnd-kit measured them when the panel scrolls: the targets are measured
 * again on scroll during a drag.
 */
function DroppablePanel({
    value,
    children,
}: PropsWithChildren<{value: LeftPanelTab}>) {
    const drop = useDropTarget({type: 'panel', id: value});
    const {droppableContainers, measureDroppableContainers} = useDndContext();
    const frame = useRef<number>(undefined);

    const onScroll = useCallback(() => {
        if (!drop.active || frame.current !== undefined) {
            return;
        }
        frame.current = requestAnimationFrame(() => {
            frame.current = undefined;
            measureDroppableContainers(
                droppableContainers.getEnabled().map(c => c.id)
            );
        });
    }, [drop.active, droppableContainers, measureDroppableContainers]);

    return (
        <TabsContent
            ref={drop.setNodeRef}
            value={value}
            className="min-h-0 flex-1 overflow-y-auto"
            data-dnd-scroll
            onScroll={onScroll}
        >
            {children}
        </TabsContent>
    );
}

'use client';

import {
    ComponentProps,
    PropsWithChildren,
    useCallback,
    useEffect,
    useMemo,
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
import {usePreferencesStore} from '@/features/preferences/store';
import {SortableSections} from './SortableSections';
import {resolveSidebarSections, SidebarSectionId} from './sidebarSections';

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
                {isAuthenticated ? <BrowseSections /> : <CollectionsPanel />}
            </DroppablePanel>
            {isAuthenticated ? (
                <DroppablePanel value="baskets">
                    <BasketsPanel />
                </DroppablePanel>
            ) : null}
        </Tabs>
    );
}

/**
 * The sections of the "Browse" tab, in the order chosen by the user: saved in
 * the preferences, and so in the profile when it is synced.
 */
function BrowseSections() {
    const {t} = useTranslation();
    const saved = usePreferencesStore(s => s.preferences.sidebarSections);
    const updatePreference = usePreferencesStore(s => s.updatePreference);
    const order = useMemo(() => resolveSidebarSections(saved), [saved]);
    const onReorder = useCallback(
        (next: SidebarSectionId[]) =>
            void updatePreference('sidebarSections', next),
        [updatePreference]
    );

    return (
        <SortableSections
            order={order}
            onReorder={onReorder}
            sections={{
                pinnedStories: <PinnedStoriesPanel />,
                savedSearches: <SavedSearchList />,
                collections: <CollectionsPanel />,
            }}
            labels={{
                pinnedStories: t('story.pinned.title', 'Pinned stories'),
                savedSearches: t('saved_search.list.title', 'Saved searches'),
                collections: t(
                    'collections.panel.title',
                    'Workspaces & collections'
                ),
            }}
        />
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

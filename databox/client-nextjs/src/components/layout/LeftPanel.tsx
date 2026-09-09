'use client';

import {useTranslation} from 'react-i18next';
import {
    FolderTreeIcon,
    ShoppingBasketIcon,
    SlidersHorizontalIcon,
} from 'lucide-react';
import {Tabs, TabsContent, TabsList, TabsTrigger} from '@/components/ui/misc';
import {useLayoutStore, LeftPanelTab} from './layoutStore';
import {useAuth} from '@/lib/auth/AuthProvider';
import {FacetsPanel} from '@/features/search/facets/FacetsPanel';
import {CollectionsPanel} from '@/features/collections/tree/CollectionsPanel';
import {BasketsPanel} from '@/features/baskets/BasketsPanel';
import {SavedSearchList} from '@/features/saved-searches/SavedSearchList';
import {useOptionalSearch} from '@/features/search/SearchProvider';

export function LeftPanel() {
    const {t} = useTranslation();
    const {isAuthenticated} = useAuth();
    const tab = useLayoutStore(s => s.leftPanelTab);
    const setTab = useLayoutStore(s => s.setLeftPanelTab);
    const search = useOptionalSearch();

    return (
        <Tabs
            value={tab}
            onValueChange={v => setTab(v as LeftPanelTab)}
            className="flex h-full min-h-0 flex-col"
        >
            <TabsList className="m-2 grid grid-cols-3">
                <TabsTrigger
                    value="facets"
                    aria-label={t('panel.facets', 'Facets')}
                >
                    <SlidersHorizontalIcon />
                    <span className="sr-only lg:not-sr-only">
                        {t('panel.facets', 'Facets')}
                    </span>
                </TabsTrigger>
                <TabsTrigger
                    value="tree"
                    aria-label={t('panel.tree', 'Navigation')}
                >
                    <FolderTreeIcon />
                    <span className="sr-only lg:not-sr-only">
                        {t('panel.tree', 'Browse')}
                    </span>
                </TabsTrigger>
                {isAuthenticated ? (
                    <TabsTrigger
                        value="baskets"
                        aria-label={t('panel.baskets', 'Baskets')}
                    >
                        <ShoppingBasketIcon />
                        <span className="sr-only lg:not-sr-only">
                            {t('panel.baskets', 'Baskets')}
                        </span>
                    </TabsTrigger>
                ) : null}
            </TabsList>
            <TabsContent
                value="facets"
                className="min-h-0 flex-1 overflow-y-auto"
            >
                {search ? <FacetsPanel /> : null}
            </TabsContent>
            <TabsContent
                value="tree"
                className="min-h-0 flex-1 overflow-y-auto"
            >
                <CollectionsPanel />
                {isAuthenticated ? <SavedSearchList /> : null}
            </TabsContent>
            {isAuthenticated ? (
                <TabsContent
                    value="baskets"
                    className="min-h-0 flex-1 overflow-y-auto"
                >
                    <BasketsPanel />
                </TabsContent>
            ) : null}
        </Tabs>
    );
}

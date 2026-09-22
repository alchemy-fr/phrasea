'use client';

import {useCallback, useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {ChevronLeftIcon, ChevronRightIcon, XIcon} from 'lucide-react';
import type {Asset, AssetRendition} from '@/types/api';
import {AssetStatus} from '@/types/api';
import {getAsset, searchAssets} from '@/lib/api/assets';
import {getAssetRenditions} from '@/lib/api/misc';
import {routes, UNKNOWN_RENDITION} from '@/lib/routes';
import {Button} from '@/components/ui/button';
import {SimpleSelect} from '@/components/ui/select';
import {Tooltip} from '@/components/ui/overlays';
import {FullPageLoader} from '@/components/ui/loader';
import {EmptyState} from '@/components/ui/misc';
import {FilePlayer} from '@/features/assets/player/FilePlayer';
import {
    AssetPanel,
    defaultAssetPanelTab,
    editPanelTarget,
    resolveAssetPanelTarget,
    type AssetPanelTab,
    type AssetPanelTarget,
} from './AssetPanel';
import {AssetViewActions} from './AssetViewActions';
import {useNavigationContextStore} from '@/features/assets/navigationContext';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {useAssetStore} from '@/features/assets/assetStore';
import {useCloseRoute} from '@/components/modals/RouteDialog';
import {QuarantineBanner} from '@/features/assets/quarantine/QuarantineBanner';
import {StoryCarousel, useStoryAssets} from './StoryCarousel';
import {useResizablePanel} from './useResizablePanel';
import {belowTopBar} from '@/components/layout/chrome';
import {usePageTrail} from '@/components/layout/layoutStore';
import {cn} from '@/lib/utils/cn';
import {isApiError} from '@/lib/api/http';

/** Best file to display for a story item standing in for its story */
function standInFile(asset: Asset | undefined) {
    return asset?.preview?.file ?? asset?.main?.file ?? asset?.thumbnail?.file;
}

/** `#panel=edit` in the URL opens the side panel on that tab or mode. */
function panelTargetFromHash(): AssetPanelTarget {
    return resolveAssetPanelTarget(
        window.location.hash.match(/^#panel=([\w-]+)$/)?.[1]
    );
}

/**
 * Full screen asset viewer: media on the left, side panel on the right.
 */
export function AssetViewRoute({
    assetId: initialAssetId,
    renditionId: initialRenditionId,
}: {
    assetId: string;
    renditionId: string;
}) {
    // Navigating between results keeps this component mounted and mirrors
    // the current asset in the URL (shallow): a router navigation would
    // trigger the intercepting route and stack a second viewer.
    const [assetId, setAssetId] = useState(initialAssetId);
    const [renditionId, setRenditionId] = useState(initialRenditionId);
    // The story being browsed, kept while switching between its items
    const [storyId, setStoryId] = useState<string>();
    // The hash is not sent to the server: the panel tab or mode it asks for
    // is read after hydration.
    const [panelTab, setPanelTab] =
        useState<AssetPanelTab>(defaultAssetPanelTab);
    const [editing, setEditing] = useState(false);
    useEffect(() => {
        setAssetId(initialAssetId);
        setRenditionId(initialRenditionId);
        setStoryId(undefined);
        const hashTarget = panelTargetFromHash();
        setEditing(hashTarget === editPanelTarget);
        if (hashTarget !== editPanelTarget) {
            setPanelTab(hashTarget);
        }
    }, [initialAssetId, initialRenditionId]);
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const close = useCloseRoute();
    const update = useAssetStore(s => s.update);
    const navContext = useNavigationContextStore(s => s.context);
    const [panelOpen, setPanelOpen] = useState(true);
    const panel = useResizablePanel();

    const queryKey = ['asset-view', assetId];
    const query = useQuery({
        queryKey,
        queryFn: async () => {
            const [asset, renditions] = await Promise.all([
                getAsset(assetId),
                getAssetRenditions(assetId),
            ]);
            update(asset);

            return {asset, renditions};
        },
        staleTime: 2000,
    });

    useChannelEvent(`asset-${assetId}`, 'asset_ingested', () =>
        queryClient.invalidateQueries({queryKey})
    );
    useChannelEvent('assets', 'rendition-update', (e: {assetId: string}) => {
        if (e.assetId === assetId) {
            void queryClient.invalidateQueries({queryKey});
        }
    });

    const asset = query.data?.asset;
    const renditions = useMemo(
        () => query.data?.renditions ?? [],
        [query.data?.renditions]
    );
    const rendition = useMemo<AssetRendition | undefined>(() => {
        if (renditionId !== UNKNOWN_RENDITION) {
            return renditions.find(r => r.id === renditionId);
        }

        return (
            renditions.find(r => r.id === asset?.preview?.id) ??
            renditions.find(r => r.file?.url) ??
            renditions[0]
        );
    }, [renditions, renditionId, asset]);

    // A story is browsed from the story itself: keep it until the URL points
    // somewhere else (the items of a story are plain assets).
    useEffect(() => {
        if (asset?.storyCollection) {
            setStoryId(asset.id);
        }
    }, [asset]);
    const story = useStoryAssets(storyId);

    // A story has no file of its own: show its first displayable item
    // instead of nothing
    const storyStandIn =
        !rendition?.file && storyId === assetId
            ? story.items.find(a => standInFile(a))
            : undefined;
    const displayedFile = rendition?.file ?? standInFile(storyStandIn);

    // prev / next navigation: inside a story it walks the story items, the
    // list the viewer was opened from otherwise
    const inStory = !!storyId && assetId !== storyId;
    const ids = useMemo(
        () => (inStory ? story.items.map(a => a.id) : (navContext?.ids ?? [])),
        [inStory, story.items, navContext?.ids]
    );
    const index = ids.indexOf(assetId);
    const prevId = index > 0 ? ids[index - 1] : undefined;
    const nextId =
        index >= 0 && index < ids.length - 1 ? ids[index + 1] : undefined;

    const goTo = useCallback(
        (id: string, target: AssetPanelTarget) => {
            setAssetId(id);
            setRenditionId(UNKNOWN_RENDITION);
            window.history.replaceState(
                window.history.state,
                '',
                routes.assetView(
                    id,
                    undefined,
                    target === defaultAssetPanelTab ? '' : `#panel=${target}`
                )
            );
        },
        [setAssetId, setRenditionId]
    );
    const panelTarget: AssetPanelTarget = editing ? editPanelTarget : panelTab;
    const go = (id: string | undefined) => {
        if (id) {
            goTo(id, panelTarget);
        }
    };
    const selectPanelTab = (tab: AssetPanelTab) => {
        setPanelTab(tab);
        setEditing(false);
        setPanelOpen(true);
        goTo(assetId, tab);
    };
    /** The pencil of the toolbar turns the edit mode of the panel on and off */
    const toggleEditing = () => {
        const next = !editing;
        setEditing(next);
        setPanelOpen(true);
        goTo(assetId, next ? editPanelTarget : panelTab);
    };

    // `#panel=edit` navigated to while the viewer is open (history, pasted
    // URL) switches the panel
    useEffect(() => {
        const onHashChange = () => {
            const hashTarget = panelTargetFromHash();
            setEditing(hashTarget === editPanelTarget);
            if (hashTarget !== editPanelTarget) {
                setPanelTab(hashTarget);
            }
            setPanelOpen(true);
        };
        window.addEventListener('hashchange', onHashChange);

        return () => window.removeEventListener('hashchange', onHashChange);
    }, []);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            const el = document.activeElement as HTMLElement | null;
            if (
                el &&
                (['INPUT', 'TEXTAREA'].includes(el.tagName) ||
                    el.closest('[data-resize-handle]'))
            ) {
                return;
            }
            if (e.key === 'ArrowLeft') {
                go(prevId);
            } else if (e.key === 'ArrowRight') {
                go(nextId);
            } else if (
                e.key === 'Escape' &&
                !document.querySelector('[role=dialog][data-state=open]')
            ) {
                close();
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [prevId, nextId, panelTarget]);

    // Prefetch neighbours
    useEffect(() => {
        [prevId, nextId].filter(Boolean).forEach(id => {
            void queryClient.prefetchQuery({
                queryKey: ['asset-view', id],
                queryFn: async () => ({
                    asset: await getAsset(id!),
                    renditions: await getAssetRenditions(id!),
                }),
            });
        });
    }, [prevId, nextId, queryClient]);

    // Where the user is, shown in the top bar
    usePageTrail(asset?.name || t('nav.asset', 'Asset'));

    const refresh = useCallback(() => {
        void queryClient.invalidateQueries({queryKey: ['asset-view', assetId]});
    }, [queryClient, assetId]);

    return (
        <div
            data-testid="asset-view"
            className={cn(
                belowTopBar,
                'z-50 flex flex-col bg-background text-foreground'
            )}
        >
            <header className="flex h-14 shrink-0 items-center gap-2 border-b px-3">
                <Tooltip content={t('common.close', 'Close')}>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={close}
                        aria-label={t('common.close', 'Close')}
                    >
                        <XIcon />
                    </Button>
                </Tooltip>
                <h1
                    data-testid="asset-view-title"
                    className="min-w-0 flex-1 truncate text-base font-semibold"
                    title={asset?.name}
                >
                    {asset?.name ?? ''}
                </h1>
                {renditions.length > 0 ? (
                    <SimpleSelect
                        size="sm"
                        className="w-48"
                        value={rendition?.id}
                        onValueChange={id => {
                            setRenditionId(id);
                            window.history.replaceState(
                                window.history.state,
                                '',
                                routes.assetView(assetId, id)
                            );
                        }}
                        options={renditions
                            .filter(r => r.file)
                            .map(r => ({
                                value: r.id,
                                label: r.displayName ?? r.name,
                            }))}
                    />
                ) : null}
                {asset ? (
                    <AssetViewActions
                        asset={asset}
                        rendition={rendition}
                        onTogglePanel={() => setPanelOpen(p => !p)}
                        onEdit={toggleEditing}
                        editing={editing}
                        panelOpen={panelOpen}
                    />
                ) : null}
            </header>

            <div className="flex min-h-0 flex-1">
                <div className="relative flex min-w-0 flex-1 flex-col">
                    {ids.length > 1 ? (
                        <>
                            <Button
                                variant="secondary"
                                size="icon"
                                className="absolute top-1/2 left-3 z-10 -translate-y-1/2 rounded-full shadow"
                                disabled={!prevId}
                                onClick={() => go(prevId)}
                                aria-label={t(
                                    'asset.view.previous',
                                    'Previous'
                                )}
                            >
                                <ChevronLeftIcon />
                            </Button>
                            <Button
                                variant="secondary"
                                size="icon"
                                className="absolute top-1/2 right-3 z-10 -translate-y-1/2 rounded-full shadow"
                                disabled={!nextId}
                                onClick={() => go(nextId)}
                                aria-label={t('asset.view.next', 'Next')}
                            >
                                <ChevronRightIcon />
                            </Button>
                        </>
                    ) : null}
                    <div className="relative flex min-h-0 flex-1 items-center justify-center bg-media-bg">
                        {query.isLoading ? <FullPageLoader /> : null}
                        {query.isError ? (
                            <EmptyState
                                title={
                                    isApiError(query.error, 404) ||
                                    isApiError(query.error, 403)
                                        ? t(
                                              'asset.view.not_found',
                                              'Asset not found or not accessible'
                                          )
                                        : t('common.error', 'An error occurred')
                                }
                                description={(query.error as Error).message}
                                action={
                                    <Button onClick={close}>
                                        {t('common.close', 'Close')}
                                    </Button>
                                }
                            />
                        ) : null}
                        {asset && displayedFile ? (
                            <div className="relative size-full">
                                <FilePlayer
                                    file={displayedFile}
                                    title={storyStandIn?.name ?? asset.name}
                                    fit="zoom"
                                    className="size-full"
                                    autoPlay
                                />
                            </div>
                        ) : asset && !query.isLoading && !story.isLoading ? (
                            <EmptyState
                                title={t(
                                    'asset.view.no_rendition',
                                    'No rendition available yet'
                                )}
                            />
                        ) : null}
                    </div>
                    {asset?.status === AssetStatus.Quarantined ? (
                        <div className="border-t p-3">
                            <QuarantineBanner asset={asset} />
                        </div>
                    ) : null}
                    {storyId ? (
                        <StoryCarousel
                            storyId={storyId}
                            currentAssetId={assetId}
                            onSelect={id => go(id)}
                        />
                    ) : null}
                </div>
                {panelOpen ? (
                    <div
                        data-resize-handle
                        role="separator"
                        aria-orientation="vertical"
                        aria-label={t(
                            'asset.view.resize_panel',
                            'Resize the panel'
                        )}
                        tabIndex={0}
                        onPointerDown={panel.onPointerDown}
                        onKeyDown={panel.onKeyDown}
                        className={cn(
                            'w-1 shrink-0 cursor-col-resize bg-border transition-colors hover:bg-primary/60 focus-visible:bg-primary focus-visible:outline-none',
                            panel.resizing && 'bg-primary'
                        )}
                    />
                ) : null}
                <aside
                    data-testid="asset-panel"
                    style={{width: panel.width}}
                    className={cn(
                        'shrink-0 overflow-hidden border-l bg-background',
                        !panelOpen && 'hidden'
                    )}
                >
                    {asset ? (
                        <AssetPanel
                            asset={asset}
                            rendition={rendition}
                            tab={panelTab}
                            onTabChange={selectPanelTab}
                            editing={editing}
                            onExitEdit={toggleEditing}
                            refresh={refresh}
                        />
                    ) : null}
                </aside>
            </div>
        </div>
    );
}

export function useSiblingsFromSearch() {
    return searchAssets;
}

export type {Asset};

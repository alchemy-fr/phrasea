'use client';

import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
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
import {AssetSidePanel} from './AssetSidePanel';
import {AssetViewActions} from './AssetViewActions';
import {useNavigationContextStore} from '@/features/assets/navigationContext';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {useAssetStore} from '@/features/assets/assetStore';
import {useCloseRoute} from '@/components/modals/RouteDialog';
import {QuarantineBanner} from '@/features/assets/quarantine/QuarantineBanner';
import {StoryCarousel} from './StoryCarousel';
import {cn} from '@/lib/utils/cn';
import {isApiError} from '@/lib/api/http';

/**
 * Full screen asset viewer: media on the left, side panel on the right.
 */
export function AssetViewRoute({
    assetId,
    renditionId,
}: {
    assetId: string;
    renditionId: string;
}) {
    const {t} = useTranslation();
    const router = useRouter();
    const queryClient = useQueryClient();
    const close = useCloseRoute();
    const update = useAssetStore(s => s.update);
    const navContext = useNavigationContextStore(s => s.context);
    const [panelOpen, setPanelOpen] = useState(true);

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

    // prev / next navigation
    const ids = navContext?.ids ?? [];
    const index = ids.indexOf(assetId);
    const prevId = index > 0 ? ids[index - 1] : undefined;
    const nextId =
        index >= 0 && index < ids.length - 1 ? ids[index + 1] : undefined;
    const go = (id: string | undefined) =>
        id && router.replace(routes.assetView(id));

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            const el = document.activeElement as HTMLElement | null;
            if (el && ['INPUT', 'TEXTAREA'].includes(el.tagName)) {
                return;
            }
            if (e.key === 'ArrowLeft') {
                go(prevId);
            } else if (e.key === 'ArrowRight') {
                go(nextId);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [prevId, nextId]);

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

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-background text-foreground">
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
                        onValueChange={id =>
                            router.replace(routes.assetView(assetId, id))
                        }
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
                        {asset && rendition?.file ? (
                            <div className="relative size-full">
                                <FilePlayer
                                    file={rendition.file}
                                    title={asset.name}
                                    fit="zoom"
                                    className="size-full"
                                    autoPlay
                                />
                            </div>
                        ) : asset && !query.isLoading ? (
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
                    {asset?.storyCollection ? (
                        <StoryCarousel asset={asset} />
                    ) : null}
                </div>
                <aside
                    className={cn(
                        'w-[400px] shrink-0 overflow-y-auto border-l bg-background',
                        !panelOpen && 'hidden'
                    )}
                >
                    {asset ? (
                        <AssetSidePanel asset={asset} rendition={rendition} />
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

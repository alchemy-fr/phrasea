'use client';

import {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {useRouter} from 'next/navigation';
import {
    useInfiniteQuery,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {
    InfoIcon,
    PencilIcon,
    PlugIcon,
    ShoppingBasketIcon,
    Trash2Icon,
    XIcon,
} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset, BasketAsset} from '@/types/api';
import {getBasket, getBasketAssets} from '@/lib/api/misc';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {FullPageLoader} from '@/components/ui/loader';
import {EmptyState} from '@/components/ui/misc';
import {AssetList} from '@/features/assets/list/AssetList';
import {SelectionProvider} from '@/features/assets/list/SelectionProvider';
import {useAssetOpener} from '@/features/assets/useAssetOpener';
import {useBasketStore} from '../basketStore';
import {BasketsPanel} from '../BasketsPanel';
import {useCloseRoute} from '@/components/modals/RouteDialog';
import {routes} from '@/lib/routes';
import {useDisplayPreferences} from '@/features/preferences/store';
import {SelectionActions} from '@/features/assets/list/toolbar/SelectionActions';
import {useOptionalSelection} from '@/features/assets/list/SelectionProvider';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';

/**
 * Full screen basket view: basket list on the left, ordered assets grid.
 */
export function BasketViewRoute({basketId}: {basketId: string}) {
    const {t} = useTranslation();
    const router = useRouter();
    const close = useCloseRoute();
    const queryClient = useQueryClient();
    const openAsset = useAssetOpener();
    const display = useDisplayPreferences();
    const removeItems = useBasketStore(s => s.removeItems);
    const upsert = useBasketStore(s => s.upsert);

    const basket = useQuery({
        queryKey: ['basket', basketId],
        queryFn: async () => {
            const b = await getBasket(basketId);
            upsert(b);

            return b;
        },
    });
    const assets = useInfiniteQuery({
        queryKey: ['basket-assets', basketId],
        queryFn: ({pageParam}) => getBasketAssets(basketId, pageParam),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    useChannelEvent(`basket-${basketId}`, 'basket:update', () => {
        void queryClient.invalidateQueries({
            queryKey: ['basket-assets', basketId],
        });
        void basket.refetch();
    });

    const pages = useMemo(
        () => (assets.data?.pages ?? []).map(p => p.items.map(i => i.asset)),
        [assets.data]
    );
    const itemsByAsset = useMemo(() => {
        const map = new Map<string, BasketAsset>();
        let position = 1;
        assets.data?.pages.forEach(p =>
            p.items.forEach(i =>
                map.set(i.asset.id, {...i, position: position++})
            )
        );

        return map;
    }, [assets.data]);

    const remove = async (list: Asset[]) => {
        const itemIds = list
            .map(a => itemsByAsset.get(a.id)?.id)
            .filter((id): id is string => !!id);
        try {
            await removeItems(basketId, itemIds);
            void queryClient.invalidateQueries({
                queryKey: ['basket-assets', basketId],
            });
            toast.success(
                t('basket.removed', '{{count}} item(s) removed from basket', {
                    count: itemIds.length,
                })
            );
        } catch (e: any) {
            toast.error(e?.message);
        }
    };

    const total = assets.data?.pages[0]?.total ?? 0;

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-background">
            <header className="flex h-14 shrink-0 items-center gap-2 border-b px-3">
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={close}
                    aria-label={t('common.close', 'Close')}
                >
                    <XIcon />
                </Button>
                <ShoppingBasketIcon className="size-5 text-muted-foreground" />
                <h1 className="min-w-0 flex-1 truncate text-base font-semibold">
                    {basket.data?.name ?? ''}
                </h1>
                <span className="text-sm text-muted-foreground">
                    {t('basket.view.count', '{{count}} item(s)', {
                        count: total,
                    })}
                </span>
                {basket.data ? (
                    <>
                        <Tooltip content={t('asset.actions.info', 'Info')}>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={() =>
                                    router.push(
                                        routes.basketManage(basketId, 'info')
                                    )
                                }
                            >
                                <InfoIcon />
                            </Button>
                        </Tooltip>
                        {basket.data.capabilities.edit ? (
                            <Tooltip content={t('common.edit', 'Edit')}>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={() =>
                                        router.push(
                                            routes.basketManage(
                                                basketId,
                                                'edit'
                                            )
                                        )
                                    }
                                >
                                    <PencilIcon />
                                </Button>
                            </Tooltip>
                        ) : null}
                        <Tooltip
                            content={t('basket.integrations', 'Integrations')}
                        >
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={() =>
                                    router.push(
                                        routes.basketManage(
                                            basketId,
                                            'integrations'
                                        )
                                    )
                                }
                            >
                                <PlugIcon />
                            </Button>
                        </Tooltip>
                    </>
                ) : null}
            </header>
            <div className="flex min-h-0 flex-1">
                <aside className="w-72 shrink-0 overflow-y-auto border-r bg-sidebar">
                    <BasketsPanel />
                </aside>
                <main className="flex min-w-0 flex-1 flex-col">
                    {basket.isLoading || assets.isLoading ? (
                        <FullPageLoader />
                    ) : basket.isError ? (
                        <EmptyState
                            title={t('basket.not_found', 'Basket not found')}
                        />
                    ) : (
                        <SelectionProvider>
                            <BasketToolbar
                                pages={pages}
                                onRemove={
                                    basket.data?.capabilities.edit
                                        ? remove
                                        : undefined
                                }
                            />
                            {total === 0 ? (
                                <EmptyState
                                    className="flex-1"
                                    icon={<ShoppingBasketIcon />}
                                    title={t(
                                        'basket.view.empty',
                                        'This basket is empty'
                                    )}
                                    description={t(
                                        'basket.view.empty_help',
                                        'Select assets in the search results and add them to the basket.'
                                    )}
                                />
                            ) : (
                                <AssetList
                                    pages={pages}
                                    loading={false}
                                    loadingMore={assets.isFetchingNextPage}
                                    hasMore={assets.hasNextPage}
                                    onLoadMore={() =>
                                        assets
                                            .fetchNextPage()
                                            .then(() => undefined)
                                    }
                                    layout={display.layout}
                                    thumbSize={display.thumbSize}
                                    onOpen={openAsset}
                                    itemOverlay={asset => (
                                        <span className="pointer-events-none absolute bottom-1.5 left-1.5 z-10 rounded bg-background/90 px-1.5 py-0.5 font-mono text-[11px] font-semibold shadow-sm">
                                            #
                                            {
                                                itemsByAsset.get(asset.id)
                                                    ?.position
                                            }
                                        </span>
                                    )}
                                    itemActions={
                                        basket.data?.capabilities.edit
                                            ? asset => (
                                                  <Tooltip
                                                      content={t(
                                                          'basket.remove_item',
                                                          'Remove from basket'
                                                      )}
                                                  >
                                                      <Button
                                                          variant="secondary"
                                                          size="icon-xs"
                                                          className="bg-background/90 shadow-sm"
                                                          onClick={() =>
                                                              remove([asset])
                                                          }
                                                      >
                                                          <Trash2Icon />
                                                      </Button>
                                                  </Tooltip>
                                              )
                                            : undefined
                                    }
                                />
                            )}
                        </SelectionProvider>
                    )}
                </main>
            </div>
        </div>
    );
}

function BasketToolbar({
    pages,
    onRemove,
}: {
    pages: Asset[][];
    onRemove?: (assets: Asset[]) => Promise<void>;
}) {
    const {t} = useTranslation();
    const selection = useOptionalSelection();

    return (
        <div className="flex min-h-10 items-center gap-2 border-b px-3 py-1">
            <SelectionActions pages={pages} context={{basket: false}} />
            {onRemove && selection && selection.selection.length > 0 ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-destructive"
                    onClick={() =>
                        onRemove(selection.selection).then(() =>
                            selection.clear()
                        )
                    }
                >
                    <Trash2Icon />{' '}
                    {t('basket.remove_item', 'Remove from basket')}
                </Button>
            ) : null}
        </div>
    );
}

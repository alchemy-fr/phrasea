'use client';

import {useEffect, useRef} from 'react';
import {useTranslation} from 'react-i18next';
import type {Asset} from '@/types/api';
import {useSelection} from './SelectionProvider';
import {useSelectAllKey} from '@/hooks/useSelectAllKey';
import {useInfiniteScroll} from '@/hooks/useInfiniteScroll';
import {GridLayout} from './layouts/GridLayout';
import {ListLayout} from './layouts/ListLayout';
import {Button} from '@/components/ui/button';
import {Spinner} from '@/components/ui/loader';
import type {LayoutMode} from '@/features/preferences/store';
import type {OpenAssetOptions} from '@/features/assets/useAssetOpener';
import {PreviewProvider} from './preview/PreviewProvider';
import {cn} from '@/lib/utils/cn';

export type OnOpenAsset = (asset: Asset, options?: OpenAssetOptions) => void;

export type AssetListProps = {
    pages: Asset[][];
    loading: boolean;
    loadingMore?: boolean;
    hasMore?: boolean;
    onLoadMore?: () => Promise<void>;
    /** increments on every new search to reset selection and scroll */
    searchGeneration?: number;
    layout: LayoutMode;
    thumbSize: number;
    onOpen?: OnOpenAsset;
    searchQuery?: string;
    /** Extra per-item overlay (e.g. basket position) */
    itemOverlay?: (asset: Asset, index: number) => React.ReactNode;
    /** Custom item actions */
    itemActions?: (asset: Asset) => React.ReactNode;
    className?: string;
    /** Disable hover preview */
    noPreview?: boolean;
};

export type LayoutProps = Omit<
    AssetListProps,
    | 'loading'
    | 'loadingMore'
    | 'hasMore'
    | 'onLoadMore'
    | 'searchGeneration'
    | 'className'
> & {
    scrollRef: React.RefObject<HTMLDivElement | null>;
    onItemClick: (asset: Asset, e: React.MouseEvent) => void;
    onItemDoubleClick: (asset: Asset) => void;
    openAsset: (asset: Asset) => void;
    footer: React.ReactNode;
};

export function AssetList(props: AssetListProps) {
    const {
        pages,
        loading,
        loadingMore,
        hasMore,
        onLoadMore,
        searchGeneration,
        layout,
        className,
        onOpen,
    } = props;
    const {t} = useTranslation();
    const selection = useSelection();
    const scrollRef = useRef<HTMLDivElement>(null);

    useSelectAllKey(() => selection.selectAll(pages));

    useEffect(() => {
        selection.clear();
        scrollRef.current?.scrollTo({top: 0});
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchGeneration]);

    const sentinelRef = useInfiniteScroll(
        scrollRef,
        onLoadMore,
        !!hasMore && !loadingMore && !loading
    );

    const allIds = pages.flat().map(a => a.id);
    const openAsset = (asset: Asset) => onOpen?.(asset, {siblings: allIds});

    const footer = (
        <div className="flex flex-col items-center py-4">
            <div ref={sentinelRef} />
            {loadingMore ? <Spinner /> : null}
            {hasMore && !loadingMore ? (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onLoadMore?.()}
                >
                    {t('common.load_more', 'Load more')}
                </Button>
            ) : null}
        </div>
    );

    const layoutProps: LayoutProps = {
        ...props,
        scrollRef,
        onItemClick: (asset, e) => selection.onItemClick(asset, pages, e),
        onItemDoubleClick: asset => openAsset(asset),
        openAsset,
        footer,
    };

    return (
        <PreviewProvider disabled={props.noPreview}>
            <div
                ref={scrollRef}
                className={cn('relative h-full overflow-y-auto', className)}
            >
                {loading ? (
                    <div className="pointer-events-none absolute inset-x-0 top-2 z-10 flex justify-center">
                        <div className="rounded-full bg-background/90 p-2 shadow-md">
                            <Spinner />
                        </div>
                    </div>
                ) : null}
                {layout === 'list' ? (
                    <ListLayout {...layoutProps} />
                ) : (
                    <GridLayout {...layoutProps} />
                )}
            </div>
        </PreviewProvider>
    );
}

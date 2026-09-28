'use client';

import {memo, useMemo} from 'react';
import type {Asset} from '@/types/api';
import type {LayoutProps} from '../AssetList';
import {buildSections, SectionDivider} from './Dividers';
import {AssetThumb} from '../AssetThumb';
import {AssetItemControls} from '../AssetItemControls';
import {AssetContextMenu} from '../AssetContextMenu';
import {SelectableCard} from '../SelectableCard';
import {assetKey} from '../SelectionProvider';
import {useLiveAsset} from '@/features/assets/assetStore';
import {Highlight} from '@/components/ui/highlight';
import {TagChip, CollectionChip, PrivacyIcon} from '@/components/chips';
import {GridCardZones, useGridProfileItems} from './GridCardZones';
import {useOptionalSearch} from '@/features/search/SearchProvider';
import {Masonry} from '@/components/ui/masonry';

/**
 * Cards of a fixed height in a grid, every page and group of results in a
 * section of its own; or — `masonry` layout — bare thumbnails keeping their
 * aspect ratio, in columns, the pages following each other without divider
 * (only groups make sections). Either way, loading more results never moves
 * the cards already displayed.
 */
export function GridLayout(props: LayoutProps) {
    const {pages, thumbSize, footer, layout} = props;
    const masonry = layout === 'masonry';
    const sections = useMemo(
        () => buildSections(masonry ? [pages.flat()] : pages),
        [pages, masonry]
    );

    const renderItem = ({asset, index}: {asset: Asset; index: number}) => (
        <GridItem
            key={assetKey(asset)}
            asset={asset}
            index={index}
            thumbSize={thumbSize}
            masonry={masonry}
            onItemClick={props.onItemClick}
            onItemDoubleClick={props.onItemDoubleClick}
            openAsset={props.openAsset}
            itemOverlay={props.itemOverlay}
            itemActions={props.itemActions}
            searchQuery={props.searchQuery}
        />
    );

    return (
        <div className="pb-4">
            {sections.map(section => (
                <div key={section.key}>
                    <SectionDivider section={section} />
                    {masonry ? (
                        <Masonry
                            className="p-3"
                            items={section.items}
                            columnWidth={thumbSize}
                            getKey={({asset}) => assetKey(asset)}
                            renderItem={renderItem}
                        />
                    ) : (
                        <div
                            className="grid gap-3 p-3"
                            style={{
                                gridTemplateColumns: `repeat(auto-fill, minmax(${thumbSize}px, 1fr))`,
                            }}
                        >
                            {section.items.map(renderItem)}
                        </div>
                    )}
                </div>
            ))}
            {footer}
        </div>
    );
}

type GridItemProps = Pick<
    LayoutProps,
    | 'thumbSize'
    | 'onItemClick'
    | 'onItemDoubleClick'
    | 'openAsset'
    | 'itemOverlay'
    | 'itemActions'
    | 'searchQuery'
> & {asset: Asset; index: number; masonry?: boolean};

const GridItem = memo(function GridItem({
    asset: initialAsset,
    index,
    thumbSize,
    masonry,
    onItemClick,
    onItemDoubleClick,
    openAsset,
    itemOverlay,
    itemActions,
    searchQuery,
}: GridItemProps) {
    // Not subscribed to the selection: see `SelectableCard`
    const asset = useLiveAsset(initialAsset);
    const search = useOptionalSearch();
    const gridItems = useGridProfileItems();
    const hasProfile = gridItems.length > 0;

    return (
        <AssetContextMenu asset={asset} onOpen={() => openAsset(asset)}>
            <SelectableCard
                asset={asset}
                // `isolate`: the overlays of the thumbnail stay under the
                // sticky section dividers
                className="group/item relative isolate flex flex-col overflow-hidden rounded-lg border bg-card text-card-foreground transition-shadow select-none hover:shadow-md"
                onItemClick={onItemClick}
                onItemDoubleClick={onItemDoubleClick}
            >
                <div
                    className="relative overflow-hidden bg-media-bg"
                    style={masonry ? undefined : {height: thumbSize}}
                >
                    <AssetThumb
                        asset={asset}
                        size={thumbSize}
                        natural={masonry}
                        previewOnHover
                    />
                    <AssetItemControls
                        asset={asset}
                        actions={itemActions?.(asset)}
                    />
                    {itemOverlay?.(asset, index)}
                    {hasProfile ? (
                        <GridCardZones
                            asset={asset}
                            items={gridItems}
                            region="over"
                            thumbSize={thumbSize}
                        />
                    ) : null}
                </div>
                {/* Masonry: the thumbnail alone */}
                {masonry ? null : hasProfile ? (
                    <GridCardZones
                        asset={asset}
                        items={gridItems}
                        region="below"
                        thumbSize={thumbSize}
                    />
                ) : (
                    <div className="flex min-h-0 flex-col gap-1 p-2">
                        <div className="flex items-start gap-1">
                            <div
                                data-testid="asset-item-title"
                                className="min-w-0 flex-1 truncate text-sm font-medium"
                                title={asset.name}
                            >
                                <Highlight
                                    text={
                                        asset.nameHighlight || asset.name || '—'
                                    }
                                />
                            </div>
                            {asset.privacy !== undefined ? (
                                <PrivacyIcon
                                    privacy={asset.privacy}
                                    className="mt-0.5 shrink-0 text-muted-foreground"
                                />
                            ) : null}
                        </div>
                        {asset.tags && asset.tags.length > 0 ? (
                            <div className="flex flex-wrap gap-1">
                                {asset.tags.slice(0, 2).map(tag => (
                                    <TagChip key={tag.id} tag={tag} />
                                ))}
                                {asset.tags.length > 2 ? (
                                    <span className="text-[11px] text-muted-foreground">
                                        +{asset.tags.length - 2}
                                    </span>
                                ) : null}
                            </div>
                        ) : null}
                        {asset.collections && asset.collections.length > 0 ? (
                            <div className="flex flex-wrap gap-1">
                                {asset.collections.slice(0, 1).map(c => (
                                    <CollectionChip
                                        key={c.id}
                                        collection={c}
                                        onClick={
                                            search
                                                ? () =>
                                                      search.selectCollection(
                                                          c.id,
                                                          c
                                                      )
                                                : undefined
                                        }
                                    />
                                ))}
                                {asset.collections.length > 1 ? (
                                    <span
                                        className="text-[11px] text-muted-foreground"
                                        title={asset.collections
                                            .map(
                                                c =>
                                                    c.absoluteDisplayName ??
                                                    c.displayName
                                            )
                                            .join('\n')}
                                    >
                                        +{asset.collections.length - 1}
                                    </span>
                                ) : null}
                            </div>
                        ) : null}
                        {searchQuery ? null : null}
                    </div>
                )}
            </SelectableCard>
        </AssetContextMenu>
    );
});

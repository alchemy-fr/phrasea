'use client';

import {memo, useMemo} from 'react';
import type {Asset} from '@/types/api';
import type {LayoutProps} from '../AssetList';
import {buildSections, SectionDivider} from './Dividers';
import {AssetThumb} from '../AssetThumb';
import {AssetItemControls} from '../AssetItemControls';
import {AssetContextMenu} from '../AssetContextMenu';
import {useSelection} from '../SelectionProvider';
import {useLiveAsset} from '@/features/assets/assetStore';
import {Highlight} from '@/components/ui/highlight';
import {TagChip, CollectionChip, PrivacyIcon} from '@/components/chips';
import {cn} from '@/lib/utils/cn';
import {usePreview} from '../preview/PreviewProvider';
import {GridCardZones, useGridProfileItems} from './GridCardZones';
import {useOptionalSearch} from '@/features/search/SearchProvider';

export function GridLayout(props: LayoutProps) {
    const {pages, thumbSize, footer} = props;
    const sections = useMemo(() => buildSections(pages), [pages]);

    return (
        <div className="pb-4">
            {sections.map(section => (
                <div key={section.key}>
                    <SectionDivider section={section} />
                    <div
                        className="grid gap-3 p-3"
                        style={{
                            gridTemplateColumns: `repeat(auto-fill, minmax(${thumbSize}px, 1fr))`,
                        }}
                    >
                        {section.items.map(({asset, index}) => (
                            <GridItem
                                key={asset.id}
                                asset={asset}
                                index={index}
                                {...props}
                            />
                        ))}
                    </div>
                </div>
            ))}
            {footer}
        </div>
    );
}

type GridItemProps = LayoutProps & {asset: Asset; index: number};

const GridItem = memo(function GridItem({
    asset: initialAsset,
    index,
    thumbSize,
    onItemClick,
    onItemDoubleClick,
    openAsset,
    itemOverlay,
    itemActions,
    searchQuery,
}: GridItemProps) {
    const asset = useLiveAsset(initialAsset);
    const selection = useSelection();
    const selected = selection.isSelected(asset.id);
    const disabled = selection.disabledIds?.has(asset.id);
    const preview = usePreview();
    const search = useOptionalSearch();
    const gridItems = useGridProfileItems();
    const hasProfile = gridItems.length > 0;

    return (
        <AssetContextMenu asset={asset} onOpen={() => openAsset(asset)}>
            <div
                data-asset-id={asset.id}
                className={cn(
                    'group/item relative flex flex-col overflow-hidden rounded-lg border bg-card text-card-foreground transition-shadow select-none hover:shadow-md',
                    selected && 'border-primary ring-2 ring-primary/40',
                    disabled && 'opacity-40'
                )}
                onClick={e => !disabled && onItemClick(asset, e)}
                onDoubleClick={() => onItemDoubleClick(asset)}
                onMouseEnter={e => preview.onEnter(asset, e.currentTarget)}
                onMouseLeave={() => preview.onLeave(asset)}
            >
                <div
                    className="relative overflow-hidden bg-media-bg"
                    style={{height: thumbSize}}
                >
                    <AssetThumb asset={asset} size={thumbSize} />
                    <AssetItemControls
                        asset={asset}
                        selected={selected}
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
                {hasProfile ? (
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
            </div>
        </AssetContextMenu>
    );
});

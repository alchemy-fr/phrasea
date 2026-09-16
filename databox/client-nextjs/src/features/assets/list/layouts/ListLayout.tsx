'use client';

import {memo, useMemo} from 'react';
import {useVirtualizer} from '@tanstack/react-virtual';
import type {Asset} from '@/types/api';
import type {LayoutProps} from '../AssetList';
import {buildSections, ListSection, SectionDivider} from './Dividers';
import {AssetThumb} from '../AssetThumb';
import {AssetItemControls} from '../AssetItemControls';
import {AssetContextMenu} from '../AssetContextMenu';
import {SelectableCard} from '../SelectableCard';
import {useLiveAsset} from '@/features/assets/assetStore';
import {Highlight} from '@/components/ui/highlight';
import {AttributeList} from '@/features/attributes/AttributeList';
import {TagChip, CollectionChip, PrivacyIcon} from '@/components/chips';
import {QuarantineBanner} from '@/features/assets/quarantine/QuarantineBanner';
import {AssetStatus} from '@/types/api';

type Row =
    | {type: 'divider'; section: ListSection}
    | {type: 'asset'; asset: Asset; index: number}
    | {type: 'footer'};

export function ListLayout(props: LayoutProps) {
    const {pages, thumbSize, scrollRef, footer} = props;

    const rows = useMemo<Row[]>(() => {
        const out: Row[] = [];
        for (const section of buildSections(pages)) {
            if (
                section.group ||
                (section.pageIndex !== undefined && section.pageIndex > 0)
            ) {
                out.push({type: 'divider', section});
            }
            section.items.forEach(({asset, index}) =>
                out.push({type: 'asset', asset, index})
            );
        }
        out.push({type: 'footer'});

        return out;
    }, [pages]);

    const virtualizer = useVirtualizer({
        count: rows.length,
        getScrollElement: () => scrollRef.current,
        estimateSize: i =>
            rows[i].type === 'asset' ? Math.max(thumbSize, 120) + 16 : 40,
        overscan: 6,
        getItemKey: i => {
            const r = rows[i];

            return r.type === 'asset'
                ? r.asset.id
                : r.type === 'divider'
                  ? `d-${r.section.key}`
                  : 'footer';
        },
    });

    return (
        <div style={{height: virtualizer.getTotalSize(), position: 'relative'}}>
            {virtualizer.getVirtualItems().map(v => {
                const row = rows[v.index];

                return (
                    <div
                        key={v.key}
                        data-index={v.index}
                        ref={virtualizer.measureElement}
                        style={{
                            position: 'absolute',
                            top: 0,
                            left: 0,
                            width: '100%',
                            transform: `translateY(${v.start}px)`,
                        }}
                    >
                        {row.type === 'divider' ? (
                            <SectionDivider
                                section={row.section}
                                sticky={false}
                            />
                        ) : row.type === 'asset' ? (
                            <ListItem
                                asset={row.asset}
                                index={row.index}
                                thumbSize={thumbSize}
                                onItemClick={props.onItemClick}
                                onItemDoubleClick={props.onItemDoubleClick}
                                openAsset={props.openAsset}
                                itemOverlay={props.itemOverlay}
                                itemActions={props.itemActions}
                            />
                        ) : (
                            footer
                        )}
                    </div>
                );
            })}
        </div>
    );
}

const ListItem = memo(function ListItem({
    asset: initialAsset,
    index,
    thumbSize,
    onItemClick,
    onItemDoubleClick,
    openAsset,
    itemOverlay,
    itemActions,
}: Pick<
    LayoutProps,
    | 'thumbSize'
    | 'onItemClick'
    | 'onItemDoubleClick'
    | 'openAsset'
    | 'itemOverlay'
    | 'itemActions'
> & {asset: Asset; index: number}) {
    // Not subscribed to the selection: see `SelectableCard`
    const asset = useLiveAsset(initialAsset);
    const size = Math.max(thumbSize, 120);

    return (
        <AssetContextMenu asset={asset} onOpen={() => openAsset(asset)}>
            <SelectableCard
                asset={asset}
                className="group/item mx-3 my-2 flex gap-3 rounded-lg border bg-card p-2 transition-shadow select-none hover:shadow-md"
                onItemClick={onItemClick}
                onItemDoubleClick={onItemDoubleClick}
            >
                <div
                    className="relative shrink-0 overflow-hidden rounded-md bg-media-bg"
                    style={{width: size, height: size}}
                >
                    <AssetThumb asset={asset} size={size} previewOnHover />
                    <AssetItemControls
                        asset={asset}
                        actions={itemActions?.(asset)}
                    />
                    {itemOverlay?.(asset, index)}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="mb-1 flex items-start gap-2">
                        <h3
                            data-testid="asset-item-title"
                            className="min-w-0 flex-1 truncate text-sm font-semibold"
                            title={asset.name}
                        >
                            <Highlight
                                text={asset.nameHighlight || asset.name || '—'}
                            />
                        </h3>
                        {asset.privacy !== undefined ? (
                            <PrivacyIcon
                                privacy={asset.privacy}
                                className="mt-1 text-muted-foreground"
                            />
                        ) : null}
                    </div>
                    {asset.tags?.length || asset.collections?.length ? (
                        <div className="mb-2 flex flex-wrap gap-1">
                            {asset.tags?.map(tag => (
                                <TagChip key={tag.id} tag={tag} />
                            ))}
                            {asset.collections?.slice(0, 3).map(c => (
                                <CollectionChip key={c.id} collection={c} />
                            ))}
                        </div>
                    ) : null}
                    {asset.status === AssetStatus.Quarantined ? (
                        <QuarantineBanner asset={asset} compact />
                    ) : null}
                    <div className="max-h-60 overflow-hidden">
                        <AttributeList asset={asset} dense pinnedOnly={false} />
                    </div>
                </div>
            </SelectableCard>
        </AssetContextMenu>
    );
});

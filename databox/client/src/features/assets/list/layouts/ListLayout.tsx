'use client';

import {
    memo,
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import {useVirtualizer} from '@tanstack/react-virtual';
import {ChevronDownIcon, ChevronUpIcon} from 'lucide-react';
import type {Asset} from '@/types/api';
import type {LayoutProps} from '../AssetList';
import {buildSections, ListSection, SectionDivider} from './Dividers';
import {AssetThumb} from '../AssetThumb';
import {AssetItemControls} from '../AssetItemControls';
import {AssetContextMenu} from '../AssetContextMenu';
import {SelectableCard} from '../SelectableCard';
import {assetKey} from '../SelectionProvider';
import {useLiveAsset} from '@/features/assets/assetStore';
import {Highlight} from '@/components/ui/highlight';
import {AttributeList} from '@/features/attributes/AttributeList';
import {TagChip, CollectionChip, PrivacyIcon} from '@/components/chips';
import {QuarantineBanner} from '@/features/assets/quarantine/QuarantineBanner';
import {AssetStatus} from '@/types/api';
import {cn} from '@/lib/utils/cn';

const NO_KEYS: ReadonlySet<string> = new Set();

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

    // The scroll container is an ancestor rendered by `AssetList`: its ref is
    // attached after this component's layout effects, so the virtualizer
    // would read `null` on mount and, without a later re-render, never
    // measure nor render any row. Hand it the element once it exists.
    const [scrollElement, setScrollElement] = useState<HTMLDivElement | null>(
        null
    );
    useEffect(() => {
        setScrollElement(scrollRef.current);
    }, [scrollRef]);

    // Rows whose attributes are expanded, by asset key: kept here since a row
    // unmounts when it is scrolled out of the virtual window, and dropped
    // with the results of a new search (a new first page).
    const [expanded, setExpanded] = useState<{
        page: Asset[] | undefined;
        keys: ReadonlySet<string>;
    }>({page: pages[0], keys: NO_KEYS});
    const expandedKeys = expanded.page === pages[0] ? expanded.keys : NO_KEYS;

    const virtualizer = useVirtualizer({
        count: rows.length,
        getScrollElement: () => scrollElement,
        estimateSize: i =>
            rows[i].type === 'asset' ? Math.max(thumbSize, 120) + 16 : 40,
        overscan: 6,
        getItemKey: i => {
            const r = rows[i];

            return r.type === 'asset'
                ? assetKey(r.asset)
                : r.type === 'divider'
                  ? `d-${r.section.key}`
                  : 'footer';
        },
    });

    const firstPage = pages[0];
    const expandedRef = useRef(expandedKeys);
    useEffect(() => {
        expandedRef.current = expandedKeys;
    }, [expandedKeys]);
    const toggleExpanded = useCallback(
        (key: string) => {
            const keys = new Set(expandedRef.current);
            if (keys.delete(key)) {
                // The row shrinks from its bottom: when its top is above the
                // fold, bring it back instead of jumping past it.
                const item = virtualizer
                    .getVirtualItems()
                    .find(v => v.key === key);
                if (
                    item &&
                    scrollElement &&
                    item.start < scrollElement.scrollTop
                ) {
                    scrollElement.scrollTop = item.start;
                }
            } else {
                keys.add(key);
            }
            expandedRef.current = keys;
            setExpanded({page: firstPage, keys});
        },
        [firstPage, virtualizer, scrollElement]
    );

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
                                expanded={expandedKeys.has(assetKey(row.asset))}
                                onToggleExpanded={toggleExpanded}
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
    expanded,
    onToggleExpanded,
}: Pick<
    LayoutProps,
    | 'thumbSize'
    | 'onItemClick'
    | 'onItemDoubleClick'
    | 'openAsset'
    | 'itemOverlay'
    | 'itemActions'
> & {
    asset: Asset;
    index: number;
    expanded: boolean;
    onToggleExpanded: (key: string) => void;
}) {
    const {t} = useTranslation();
    // Not subscribed to the selection: see `SelectableCard`
    const asset = useLiveAsset(initialAsset);
    const size = Math.max(thumbSize, 120);
    const attributesRef = useRef<HTMLDivElement>(null);
    const overflowing = useOverflowing(attributesRef, expanded);

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
                    <div
                        ref={attributesRef}
                        data-testid="asset-item-attributes"
                        className={cn(
                            !expanded && 'max-h-60 overflow-hidden',
                            !expanded &&
                                overflowing &&
                                'mask-b-from-80% mask-b-to-100%'
                        )}
                    >
                        <AttributeList asset={asset} dense pinnedOnly={false} />
                    </div>
                    {expanded || overflowing ? (
                        <button
                            type="button"
                            data-testid="asset-item-attributes-toggle"
                            aria-expanded={expanded}
                            className="mt-1 inline-flex items-center gap-1 rounded text-xs font-medium text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                            // Neither select, drag nor open the asset
                            onPointerDown={e => e.stopPropagation()}
                            onClick={e => {
                                e.stopPropagation();
                                onToggleExpanded(assetKey(asset));
                            }}
                            onDoubleClick={e => e.stopPropagation()}
                        >
                            {expanded ? (
                                <>
                                    <ChevronUpIcon className="size-3.5" />
                                    {t(
                                        'asset.attributes.show_less',
                                        'Show fewer attributes'
                                    )}
                                </>
                            ) : (
                                <>
                                    <ChevronDownIcon className="size-3.5" />
                                    {t(
                                        'asset.attributes.show_all',
                                        'Show all attributes'
                                    )}
                                </>
                            )}
                        </button>
                    ) : null}
                </div>
            </SelectableCard>
        </AssetContextMenu>
    );
});

/**
 * Whether the content of a height-clamped box is cropped. Only measured while
 * clamped (`expanded` false): the box then keeps its fixed height and the
 * content is observed to follow late updates (attributes, fonts…).
 */
function useOverflowing(
    ref: React.RefObject<HTMLDivElement | null>,
    expanded: boolean
): boolean {
    const [overflowing, setOverflowing] = useState(false);

    useLayoutEffect(() => {
        const el = ref.current;
        if (!el || expanded) {
            return;
        }
        const check = () =>
            setOverflowing(el.scrollHeight > el.clientHeight + 1);
        check();
        if (typeof ResizeObserver === 'undefined') {
            return;
        }
        const observer = new ResizeObserver(check);
        observer.observe(el);
        if (el.firstElementChild) {
            observer.observe(el.firstElementChild);
        }

        return () => observer.disconnect();
    }, [ref, expanded]);

    return overflowing;
}

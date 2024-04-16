import {LayoutProps} from '../../types';
import {Asset, AssetOrAssetContainer} from '../../../../types';
import PreviewPopover from '../../PreviewPopover';
import {usePreview} from '../../usePreview';
import {DisplayContext} from '../../../Media/DisplayContext';
import React from 'react';
import assetClasses from '../../classes';
import Box from '@mui/material/Box';
import {alpha, Theme} from '@mui/material';
import {attributesSx} from '../../../Media/Asset/Attribute/Attributes';
import {tagListSx} from '../../../Media/Asset/Widgets/AssetTagList';
import {collectionListSx} from '../../../Media/Asset/Widgets/AssetCollectionList';
import {AutoSizer, CellMeasurer, List, ListRowRenderer,} from 'react-virtualized';
import AssetItem from './AssetItem.tsx';
import GroupRow from '../GroupRow.tsx';
import {menuHeight} from '../../../Layout/MainAppBar.tsx';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts';
import {CellMeasurerCache} from 'react-virtualized/dist/es/CellMeasurer';
import LoadMoreButton from '../../LoadMoreButton.tsx';
import {thumbSx} from '../../../Media/Asset/AssetThumb.tsx';
import {ScrollParams} from 'react-virtualized/dist/es/Grid';
import VirtualizedGroups from '../VirtualizedGroups.tsx';
import PageDivider from "../../PageDivider.tsx";
import {getPage} from "../page.ts";

export default function ListLayout<Item extends AssetOrAssetContainer>({
    toolbarHeight,
    pages,
    onToggle,
    onContextMenuOpen,
    onAddToBasket,
    itemComponent,
    onOpen,
    loadMore,
    selection,
    itemToAsset,
}: LayoutProps<Item>) {
    const {previewAnchorEl, onPreviewToggle} = usePreview([pages]);
    const headersRef = React.useRef<HTMLDivElement | null>(null);
    const listRef = React.useRef<List | null>(null);
    const d = React.useContext(DisplayContext)!;
    const {innerHeight} = useWindowSize();
    const height = innerHeight - toolbarHeight - menuHeight;
    const firstItem: Item | undefined = pages[0][0];
    const firstAsset = firstItem
        ? itemToAsset
            ? itemToAsset(firstItem)
            : (firstItem as unknown as Asset)
        : undefined;
    const hasGroups = Boolean(firstAsset?.groupValue);

    React.useLayoutEffect(() => {
        listRef.current?.scrollToRow(0);
    }, [pages[0], listRef]);

    const cellMeasurer = React.useMemo(() => {
        return new CellMeasurerCache({
            fixedWidth: true,
            minHeight: d.thumbSize + 20,
            defaultHeight: 400,
        });
    }, [pages[0], d.thumbSize]);

    const layoutSx = React.useCallback(
        (theme: Theme) => {
            return {
                ...tagListSx(),
                ...collectionListSx(),
                ...attributesSx(),
                ...thumbSx(d.thumbSize, theme),
                [`.${assetClasses.item}`]: {
                    'p': 2,
                    'position': 'relative',
                    [`.${assetClasses.checkBtb}, .${assetClasses.controls}`]: {
                        position: 'absolute',
                        zIndex: 2,
                        opacity: 0,
                        transform: `translateY(-10px)`,
                        transition: theme.transitions.create(
                            ['opacity', 'transform'],
                            {duration: 300}
                        ),
                    },
                    [`.${assetClasses.checkBtb}`]: {
                        transform: `translateX(-10px)`,
                        left: 15,
                        top: 15,
                    },
                    [`.${assetClasses.controls}`]: {
                        position: 'absolute',
                        right: 1,
                        top: 1,
                    },
                    '&:hover, &.selected': {
                        [`.${assetClasses.checkBtb}, .${assetClasses.controls}`]:
                            {
                                opacity: 1,
                                transform: `translateY(0)`,
                            },
                    },
                    '&.selected': {
                        backgroundColor: alpha(theme.palette.primary.main, 0.1),
                    },
                    [`.${assetClasses.attributes}`]: {
                        '> div + div': {
                            mt: 1,
                        },
                    },
                },
            };
        },
        [d]
    );

    const rowCount = pages.reduce((c, p) => c + p.length, 0);

    const rowRenderer: ListRowRenderer = ({index, key, style, parent}) => {
        const {
            pageIndex,
            itemIndex,
            item,
        } = getPage(pages, index);

        const asset: Asset = itemToAsset
            ? itemToAsset(item)
            : (item as unknown as Asset);

        return (
            <CellMeasurer
                cache={cellMeasurer}
                columnIndex={0}
                key={key}
                parent={parent}
                rowIndex={index}
            >
                {({registerChild}) => (
                    <div
                        style={style}
                        // @ts-expect-error Element | undefined
                        ref={registerChild}
                    >
                        {pageIndex > 0 && itemIndex === 0 ? (
                            <PageDivider
                                top={toolbarHeight}
                                page={pageIndex + 1}
                            />
                        ) : (
                            ''
                        )}
                        <GroupRow asset={asset} top={0}>
                            <div
                                onDoubleClick={
                                    onOpen && asset.original
                                        ? () =>
                                              onOpen(asset, asset.original!.id)
                                        : undefined
                                }
                                onContextMenu={
                                    onContextMenuOpen
                                        ? e => onContextMenuOpen!(e, item)
                                        : undefined
                                }
                            >
                                <AssetItem
                                    asset={asset}
                                    itemComponent={itemComponent}
                                    item={item}
                                    onToggle={onToggle}
                                    selected={selection.includes(item)}
                                    onAddToBasket={onAddToBasket}
                                    onContextMenuOpen={onContextMenuOpen}
                                    displayAttributes={d.displayAttributes}
                                    onPreviewToggle={onPreviewToggle}
                                />
                                {loadMore && index === rowCount - 1 ? (
                                    <LoadMoreButton
                                        onClick={() => {
                                            loadMore!().then(() => {
                                                cellMeasurer.clear(index, 0);
                                                parent.recomputeGridSize!({
                                                    rowIndex: index,
                                                    columnIndex: 0,
                                                });
                                                parent.forceUpdate();
                                            });
                                        }}
                                        pages={pages}
                                    />
                                ) : (
                                    ''
                                )}
                            </div>
                        </GroupRow>
                    </div>
                )}
            </CellMeasurer>
        );
    };

    const onScroll = (params: ScrollParams) => {
        const r = headersRef.current;
        if (r) {
            r.style.height = `${params.clientHeight}px`;
            r.scrollTo({
                top: params.scrollTop,
            });
        }
    };

    return (
        <Box sx={layoutSx}>
            <AutoSizer disableHeight>
                {({width}) => (
                    <>
                        <List
                            ref={listRef}
                            className={assetClasses.scrollable}
                            deferredMeasurementCache={cellMeasurer}
                            height={height}
                            overscanRowCount={5}
                            rowCount={rowCount}
                            rowHeight={cellMeasurer.rowHeight}
                            rowRenderer={rowRenderer}
                            width={width}
                            onScroll={onScroll}
                        />

                        {pages.length > 1 || hasGroups ? (
                            <VirtualizedGroups
                                hasGroups={hasGroups}
                                ref={headersRef}
                                height={height}
                                cellMeasurer={cellMeasurer}
                                itemToAsset={itemToAsset}
                                pages={pages}
                            />
                        ) : (
                            ''
                        )}
                    </>
                )}
            </AutoSizer>

            <PreviewPopover
                key={previewAnchorEl?.asset.id ?? 'none'}
                asset={previewAnchorEl?.asset}
                anchorEl={previewAnchorEl?.anchorEl}
                displayAttributes={d.displayAttributes}
            />
        </Box>
    );
}

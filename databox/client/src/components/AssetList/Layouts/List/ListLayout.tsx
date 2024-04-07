import {LayoutProps} from '../../types';
import {Asset, AssetOrAssetContainer} from '../../../../types';
import PreviewPopover from '../../PreviewPopover';
import {usePreview} from '../../usePreview';
import {DisplayContext} from '../../../Media/DisplayContext';
import React from 'react';
import assetClasses from '../../classes';
import {thumbSx} from '../../../Media/Asset/Thumb';
import Box from '@mui/material/Box';
import {Theme} from '@mui/material';
import {attributesSx} from '../../../Media/Asset/Attribute/Attributes';
import {tagListSx} from '../../../Media/Asset/Widgets/AssetTagList';
import {collectionListSx} from '../../../Media/Asset/Widgets/AssetCollectionList';
import {AutoSizer, CellMeasurer, List, ListRowRenderer} from 'react-virtualized';
import AssetItem from "./AssetItem.tsx";
import GroupRow from "../GroupRow.tsx";
import {menuHeight} from "../../../Layout/MainAppBar.tsx";
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts'
import {CellMeasurerCache} from "react-virtualized/dist/es/CellMeasurer";
import LoadMoreButton from "../../LoadMoreButton.tsx";
import {useScrollTopPages} from "../../useScrollTopPages.ts";
import SectionDivider from "../../SectionDivider.tsx";

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
    const listRef = React.useRef<HTMLDivElement>();
    const d = React.useContext(DisplayContext)!;
    const {innerHeight} = useWindowSize();
    const height = innerHeight - toolbarHeight - menuHeight;

    useScrollTopPages(listRef.current?.querySelector(`.${assetClasses.scrollable}`), pages);

    const cellMeasurer = React.useMemo(() => {
        return new CellMeasurerCache({
            fixedWidth: true,
            minHeight: d.thumbSize + 20,
            defaultHeight: 500
        })
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
                        boxShadow: theme.shadows[2],
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

    const rowRenderer: ListRowRenderer = ({
        index,
        key,
        style,
        parent,
    }) => {
        const perPage = pages[0].length;
        const page = Math.floor(index / perPage);
        const pageIndex = index % perPage;
        const item = pages[page][pageIndex]!;

        const asset: Asset = itemToAsset
            ? itemToAsset(item)
            : (item as unknown as Asset);

        return <CellMeasurer
            cache={cellMeasurer}
            columnIndex={0}
            key={key}
            parent={parent}
            rowIndex={index}
        >
            {({ registerChild }) => (
            <GroupRow
                asset={asset}
                toolbarHeight={toolbarHeight}
            >
                <div
                    onDoubleClick={
                        onOpen && asset.original
                            ? () => onOpen(asset, asset.original!.id)
                            : undefined
                    }
                    onContextMenu={
                        onContextMenuOpen
                            ? e => onContextMenuOpen!(e, item)
                            : undefined
                    }
                    style={style}
                    // @ts-expect-error Element | undefined
                    ref={registerChild}
                >
                    {page > 0 && pageIndex === 0 ? <SectionDivider
                        top={toolbarHeight}
                        textStyle={() => ({
                            fontWeight: 700,
                            fontSize: 15,
                        })}
                    >
                        # {page + 1}
                    </SectionDivider> : ''}
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
                    {loadMore && index === rowCount - 1 ? <LoadMoreButton
                        onClick={loadMore}
                        pages={pages}
                    /> : ''}
                </div>
            </GroupRow>)}
        </CellMeasurer>
    }

    return (
        <Box
            sx={layoutSx}
            ref={listRef}
        >
            <AutoSizer disableHeight>
                {({width}) => (
                    <List
                        className={assetClasses.scrollable}
                        deferredMeasurementCache={cellMeasurer}
                        height={height}
                        overscanRowCount={5}
                        rowCount={rowCount}
                        rowHeight={cellMeasurer.rowHeight}
                        rowRenderer={rowRenderer}
                        width={width}
                    />
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

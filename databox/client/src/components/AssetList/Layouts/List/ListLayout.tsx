import {LayoutProps} from '../../types';
import {Asset, AssetOrAssetContainer} from '../../../../types';
import ListPage from './ListPage';
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
import {List, AutoSizer} from 'react-virtualized';
import AssetItem from "./AssetItem.tsx";
import GroupRow from "../GroupRow.tsx";

export default function ListLayout<Item extends AssetOrAssetContainer>({
    toolbarHeight,
    pages,
    onToggle,
    onContextMenuOpen,
    onAddToBasket,
    itemComponent,
    onOpen,
    selection,
    itemToAsset,
}: LayoutProps<Item>) {
    const {previewAnchorEl, onPreviewToggle} = usePreview([pages]);
    const d = React.useContext(DisplayContext)!;

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

    return (
        <Box sx={layoutSx}>
            <AutoSizer disableHeight>
                {({width}) => (
                    <List
                        ref="List"
                        height={500}
                        overscanRowCount={3}
                        rowCount={pages.reduce((c, p) => c + p.length, 0)}
                        rowHeight={150}
                        rowRenderer={({
                            index,
                        }) => {
                            const item = pages[0][index];
                            const asset: Asset = itemToAsset
                                ? itemToAsset(item)
                                : (item as unknown as Asset);

                            return <GroupRow
                                key={item.id}
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
                                </div>
                            </GroupRow>
                        }}
                        width={width}
                    />
                )}
            </AutoSizer>

            {/*{pages.map((page, i) => (*/}
            {/*    <ListPage*/}
            {/*        key={i}*/}
            {/*        page={i + 1}*/}
            {/*        toolbarHeight={toolbarHeight}*/}
            {/*        items={page}*/}
            {/*        itemToAsset={itemToAsset}*/}
            {/*        onToggle={onToggle}*/}
            {/*        onPreviewToggle={onPreviewToggle}*/}
            {/*        onContextMenuOpen={onContextMenuOpen}*/}
            {/*        onAddToBasket={onAddToBasket}*/}
            {/*        itemComponent={itemComponent}*/}
            {/*        onOpen={onOpen}*/}
            {/*        selection={selection}*/}
            {/*        displayAttributes={d.displayAttributes}*/}
            {/*    />*/}
            {/*))}*/}

            <PreviewPopover
                key={previewAnchorEl?.asset.id ?? 'none'}
                asset={previewAnchorEl?.asset}
                anchorEl={previewAnchorEl?.anchorEl}
                displayAttributes={d.displayAttributes}
            />
        </Box>
    );
}

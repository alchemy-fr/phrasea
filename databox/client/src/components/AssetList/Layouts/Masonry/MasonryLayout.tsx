import {LayoutProps} from '../../types';
import {Asset, AssetOrAssetContainer} from '../../../../types';
import PreviewPopover from '../../PreviewPopover';
import {usePreview} from '../../usePreview';
import AssetItem from './AssetItem';
import React, {useContext} from 'react';
import {alpha, CircularProgress, Theme} from '@mui/material';
import assetClasses from '../../classes';
import {DisplayContext} from '../../../Media/DisplayContext';
import Box from '@mui/material/Box';
import {CellMeasurer, CellMeasurerCache, CellRenderer, createMasonryCellPositioner, Masonry} from 'react-virtualized';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts'
import {leftPanelWidth} from "../../../../themes/base.ts";
import {menuHeight} from "../../../Layout/MainAppBar.tsx";
import LoadMoreButton from "../../LoadMoreButton.tsx";
import {createSizeTransition, thumbSx} from "../../../Media/Asset/AssetThumb.tsx";

export default function MasonryLayout<Item extends AssetOrAssetContainer>({
    pages,
    onToggle,
    onContextMenuOpen,
    onOpen,
    toolbarHeight,
    onAddToBasket,
    loadMore,
    selection,
    itemToAsset,
    itemComponent,
}: LayoutProps<Item>) {
    const {previewAnchorEl, onPreviewToggle} = usePreview([pages]);
    const {innerWidth, innerHeight} = useWindowSize();
    const d = useContext(DisplayContext)!;
    const masonryWidth = innerWidth - leftPanelWidth;
    const masonryHeight = innerHeight - toolbarHeight - menuHeight;
    const columnWidth = d.thumbSize;
    const spacer = 8;
    const colCount = Math.floor(masonryWidth / (columnWidth + spacer));
    const defaultHeight = columnWidth * 2 / 3;
    const [loading, setLoading] = React.useState(true);
    const masonryRef = React.useRef<Masonry>(null);
    const flatPages = React.useMemo(() => pages.flat(), [pages]);
    const sizes = React.useRef<Record<string, {
        width: number;
        height: number;
    }>>({});

    const layoutSx = React.useCallback(
        (theme: Theme) => {
            return {
                backgroundColor: theme.palette.common.white,
                ...thumbSx(columnWidth, theme, {
                    height: 'auto',
                    img: {
                        width: columnWidth,
                        maxWidth: 'unset',
                    },
                    '&:empty': {
                        height: defaultHeight,
                    }
                }),
                [`.${assetClasses.fileIcon}`]: {
                    m: 5,
                },
                [`.${assetClasses.item}`]: {
                    'width': columnWidth,
                    'transition': createSizeTransition(theme),
                    'position': 'relative',
                    'backgroundColor': theme.palette.grey[100],
                    [`.${assetClasses.controls}`]: {
                        position: 'absolute',
                        transform: `translateY(-10px)`,
                        zIndex: 2,
                        opacity: 0,
                        left: 0,
                        top: 0,
                        right: 0,
                        padding: '1px',
                        transition: theme.transitions.create(
                            ['opacity', 'transform'],
                            {duration: 300}
                        ),
                        background:
                            'linear-gradient(to bottom, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.5) 50%, rgba(255,255,255,0) 100%)',
                    },
                    [`.${assetClasses.settingBtn}`]: {
                        position: 'absolute',
                        right: 1,
                        top: 5,
                    },
                    [`.${assetClasses.cartBtn}`]: {
                        position: 'absolute',
                        right: 40,
                        top: 5,
                    },
                    '&:hover, &.selected': {
                        [`.${assetClasses.controls}`]: {
                            opacity: 1,
                            transform: `translateY(0)`,
                        },
                    },
                    '&.selected': {
                        backgroundColor: alpha(theme.palette.primary.main, 0.8),
                        boxShadow: theme.shadows[2],
                        [`.${assetClasses.legend}`]: {
                            color: theme.palette.primary.contrastText,
                        },
                        [`.${assetClasses.thumbWrapper}::after`]: {
                            display: 'block',
                            content: '""',
                            position: 'absolute',
                            zIndex: 1,
                            top: 0,
                            left: 0,
                            bottom: 0,
                            right: 0,
                            backgroundColor: alpha(
                                theme.palette.primary.main,
                                0.3
                            ),
                        },
                    },
                },
                [`.${assetClasses.thumbActive}`]: {
                    display: 'none',
                },
            };
        },
        [columnWidth, defaultHeight]
    );

    const cache = React.useMemo(() => new CellMeasurerCache({
        defaultHeight,
        defaultWidth: columnWidth,
        fixedWidth: true,
        fixedHeight: false,
    }), [columnWidth, defaultHeight]);

    const cellPositionerParams = React.useMemo(() => ({
        cellMeasurerCache: cache,
        columnCount: colCount,
        columnWidth: columnWidth,
        spacer,
    }), [cache, colCount, spacer, columnWidth]);
    const cellPositioner = React.useMemo(() => createMasonryCellPositioner(cellPositionerParams),
        [cellPositionerParams]);

    const itemCount = flatPages.length;

    const cellRenderer: CellRenderer = React.useMemo(() => ({index, key, parent, style}) => {
        const item = flatPages[index]!;
        if (!item) {
            return <></>
        }

        const asset: Asset = itemToAsset
            ? itemToAsset(item)
            : (item as unknown as Asset);

        const size = sizes.current[item.id];
        const height = size ? columnWidth * (size.height / size.width) : (asset.original?.file ? defaultHeight : defaultHeight);

        return (
            <CellMeasurer
                cache={cache}
                index={index}
                key={key}
                parent={parent}
            >
                {({registerChild}) => (
                    <div
                        style={style}
                        // @ts-expect-error Element | undefined
                        ref={registerChild}
                    >
                        <div style={{
                            width: columnWidth,
                            height,
                        }}
                             onContextMenu={
                                 onContextMenuOpen
                                     ? e => onContextMenuOpen!(e, item)
                                     : undefined
                             }
                        >
                            <AssetItem
                                itemComponent={itemComponent}
                                item={item}
                                asset={asset}
                                onAddToBasket={onAddToBasket}
                                selected={selection.includes(item)}
                                onContextMenuOpen={onContextMenuOpen}
                                onOpen={onOpen}
                                onToggle={onToggle}
                                onPreviewToggle={onPreviewToggle}
                            />
                            {loadMore && index === itemCount - 1 ? <LoadMoreButton
                                onClick={() => {
                                    loadMore!().then(() => {
                                        parent.recomputeGridSize!({
                                            rowIndex: index,
                                            columnIndex: 0,
                                        });
                                    });
                                }}
                                pages={pages}
                            /> : ''}
                        </div>
                    </div>)}
            </CellMeasurer>
        );
    }, [cache, cellPositioner, flatPages, selection, onContextMenuOpen]);

    React.useEffect(() => {
        setLoading(true);
        Promise.all(flatPages.map(async (item): Promise<void> => {
            const asset: Asset = itemToAsset
                ? itemToAsset(item)
                : (item as unknown as Asset);

            const file = asset.thumbnail?.file;
            if (file?.type.startsWith('image/') && file!.url) {
                return new Promise((resolve): void => {
                    const img = new Image();
                    img.onload = function () {
                        const i = this as unknown as HTMLImageElement;
                        if (i.width && i.height) {
                            sizes.current[item.id] = {
                                width: i.width,
                                height: i.height,
                            }
                        }
                        resolve();
                    };
                    img.src = file!.url!;
                })
            }

            return Promise.resolve();
        })).then(() => {
            cellPositioner.reset(cellPositionerParams);
            masonryRef.current?.clearCellPositions();
            masonryRef.current?.recomputeCellPositions();
            cache.clearAll();
            setLoading(false);
        })
    }, [flatPages, masonryRef, cache, cellPositioner, cellPositionerParams]);

    if (loading) {
        return <div style={{
            width: masonryWidth,
            height: masonryHeight,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
        }}>
            <CircularProgress/>
        </div>
    }

    return (
        <Box
            sx={layoutSx}
        >
            <Masonry
                className={assetClasses.scrollable}
                overscanByPixels={1000}
                autoHeight={false}
                ref={masonryRef}
                cellCount={itemCount}
                cellMeasurerCache={cache}
                cellPositioner={cellPositioner}
                cellRenderer={cellRenderer}
                width={masonryWidth}
                height={masonryHeight}
            />

            <PreviewPopover
                key={previewAnchorEl?.asset.id ?? 'none'}
                asset={previewAnchorEl?.asset}
                anchorEl={previewAnchorEl?.anchorEl}
                displayAttributes={true}
            />
        </Box>
    );
}


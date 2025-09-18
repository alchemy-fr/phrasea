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
import {
    CellMeasurer,
    CellMeasurerCache,
    CellRenderer,
    createMasonryCellPositioner,
    Masonry,
} from 'react-virtualized';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts';
import {leftPanelWidth} from '../../../../themes/base.ts';
import {menuHeight} from '../../../Layout/MainAppBar.tsx';
import LoadMoreButton from '../../LoadMoreButton.tsx';
import {
    createSizeTransition,
    thumbSx,
} from '../../../Media/Asset/AssetThumb.tsx';
import {FileTypeEnum, getFileTypeFromMIMEType} from '../../../../lib/file.ts';

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
    previewZIndex,
}: LayoutProps<Item>) {
    const {previewAnchorEl, onPreviewToggle} = usePreview([pages]);
    const {innerWidth, innerHeight} = useWindowSize();
    const d = useContext(DisplayContext)!.state;
    const masonryWidth = innerWidth - leftPanelWidth;
    const masonryHeight = innerHeight - toolbarHeight - menuHeight;
    const columnWidth = d.thumbSize;
    const spacer = 8;
    const colCount = Math.floor(masonryWidth / (columnWidth + spacer));
    const defaultHeight = (columnWidth * 2) / 3;
    const masonryRef = React.useRef<Masonry>(null);
    const [computedItems, setComputedItems] = React.useState<Item[]>([]);
    const sizes = React.useRef<Record<string, number>>({});

    const layoutSx = React.useCallback(
        (theme: Theme) => {
            return {
                backgroundColor: theme.palette.common.white,
                ...thumbSx(columnWidth, theme, {
                    'height': 'auto',
                    'img': {
                        width: columnWidth,
                        maxWidth: 'unset',
                    },
                    '&:empty': {
                        height: defaultHeight,
                    },
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

    const cache = React.useMemo(
        () =>
            new CellMeasurerCache({
                defaultHeight,
                defaultWidth: columnWidth,
                fixedWidth: true,
                fixedHeight: false,
            }),
        []
    );

    const cellPositioner = React.useMemo(
        () =>
            createMasonryCellPositioner({
                cellMeasurerCache: cache,
                columnCount: colCount,
                columnWidth: columnWidth,
                spacer,
            }),
        [cache]
    );

    const itemCount = computedItems.length;

    const cellRenderer: CellRenderer = React.useMemo(
        () =>
            ({index, key, parent, style}) => {
                const item = computedItems[index]!;
                if (!item) {
                    return <></>;
                }

                const asset: Asset = itemToAsset
                    ? itemToAsset(item)
                    : (item as unknown as Asset);

                const ratio: number | undefined = sizes.current[asset.id];
                const height = ratio ? columnWidth * ratio : defaultHeight;

                return (
                    <CellMeasurer
                        cache={cache}
                        index={index}
                        key={key}
                        parent={parent}
                    >
                        {({registerChild}) => (
                            <div
                                style={{
                                    ...style,
                                    width: columnWidth,
                                }}
                                ref={registerChild}
                            >
                                <div
                                    style={{
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
                                    {loadMore && index === itemCount - 1 ? (
                                        <LoadMoreButton
                                            onClick={() => {
                                                loadMore!().then(() => {
                                                    parent.recomputeGridSize &&
                                                        parent.recomputeGridSize!(
                                                            {
                                                                rowIndex: index,
                                                                columnIndex: 0,
                                                            }
                                                        );
                                                });
                                            }}
                                            pages={pages}
                                        />
                                    ) : (
                                        ''
                                    )}
                                </div>
                            </div>
                        )}
                    </CellMeasurer>
                );
            },
        [computedItems, selection, onContextMenuOpen]
    );

    React.useEffect(() => {
        if (masonryRef.current) {
            cache.clearAll();
            computedItems.map((item, index): void => {
                const asset: Asset = itemToAsset
                    ? itemToAsset(item)
                    : (item as unknown as Asset);
                const ratio = sizes.current[asset.id];

                cache.set(
                    index,
                    0,
                    columnWidth,
                    ratio ? columnWidth * ratio : defaultHeight
                );
            });

            cellPositioner.reset({
                columnCount: colCount,
                columnWidth: columnWidth,
                spacer,
            });

            masonryRef.current.recomputeCellPositions();
        }
    }, [computedItems, colCount, columnWidth, spacer]);

    React.useEffect(() => {
        const flatPages = pages.flat();

        Promise.all(
            flatPages.map(async (item): Promise<void> => {
                const asset: Asset = itemToAsset
                    ? itemToAsset(item)
                    : (item as unknown as Asset);

                if (!sizes.current[asset.id]) {
                    const file = asset.thumbnail?.file;
                    if (file?.url) {
                        const mainType = getFileTypeFromMIMEType(file!.type);

                        switch (mainType) {
                            case FileTypeEnum.Image:
                                return new Promise((resolve): void => {
                                    const img = new Image();
                                    img.onload = function () {
                                        const i =
                                            this as unknown as HTMLImageElement;
                                        if (i.width && i.height) {
                                            sizes.current[asset.id] =
                                                i.height / i.width;
                                        }
                                        resolve();
                                    };
                                    img.src = file!.url!;
                                });
                            case FileTypeEnum.Audio:
                            case FileTypeEnum.Video:
                                sizes.current[asset.id] = 1;
                                break;
                        }
                    } else {
                        sizes.current[asset.id] = 1;
                    }
                }

                return Promise.resolve();
            })
        ).then(() => {
            flatPages.map((item, index): void => {
                const asset: Asset = itemToAsset
                    ? itemToAsset(item)
                    : (item as unknown as Asset);
                const ratio = sizes.current[asset.id];
                if (ratio) {
                    cache.set(
                        index,
                        0,
                        columnWidth,
                        ratio ? columnWidth * ratio : defaultHeight
                    );
                }
            });
            // @ts-expect-error not defined
            masonryRef.current._populatePositionCache(0, flatPages.length - 1);

            setComputedItems(flatPages);
        });
    }, [pages]);

    return (
        <>
            {pages.length > 0 && computedItems.length === 0 ? (
                <div
                    style={{
                        position: 'absolute',
                        left: 0,
                        right: 0,
                        top: 0,
                        bottom: 0,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <CircularProgress />
                </div>
            ) : (
                ''
            )}
            <Box sx={layoutSx}>
                <Masonry
                    className={assetClasses.scrollable}
                    overscanByPixels={masonryHeight * 2}
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
                    zIndex={previewZIndex}
                />
            </Box>
        </>
    );
}

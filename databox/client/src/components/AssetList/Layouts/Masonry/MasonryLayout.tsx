import {LayoutProps} from "../../types.ts";
import {Asset, AssetOrAssetContainer} from "../../../../types.ts";
import PreviewPopover from "../../PreviewPopover.tsx";
import {usePreview} from "../../usePreview.ts";
import Masonry from '@mui/lab/Masonry';
import AssetItem from "./AssetItem.tsx";
import React, {useContext} from "react";
import {alpha, Theme} from "@mui/material";
import assetClasses from "../../classes.ts";
import {createSizeTransition, thumbSx} from "../../../Media/Asset/Thumb.tsx";
import {DisplayContext} from "../../../Media/DisplayContext.tsx";
import Box from "@mui/material/Box";

export default function MasonryLayout<Item extends AssetOrAssetContainer>({
    pages,
    onToggle,
    onContextMenuOpen,
    onOpen,
    onAddToBasket,
    selection,
    itemToAsset,
    itemComponent,
}: LayoutProps<Item>) {
    const {previewAnchorEl, onPreviewToggle} = usePreview([pages]);
    const d = useContext(DisplayContext)!;

    const layoutSx = React.useCallback((theme: Theme) => {
        return {
            backgroundColor: theme.palette.common.white,
            [`.${assetClasses.thumbWrapper}`]: {
                ...thumbSx(d.thumbSize)(theme),
                height: 'auto',
                img: {
                    width: d.thumbSize,
                    maxWidth: 'unset',
                },
            },
            [`.${assetClasses.item}`]: {
                'width': d.thumbSize,
                'transition': createSizeTransition(theme),

                'position': 'relative',
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
                        backgroundColor: alpha(theme.palette.primary.main, 0.3),
                    },
                },
            },
            [`.${assetClasses.thumbActive}`]: {
                display: 'none',
            },
        };
    }, [d]);


    return (
        <Box
            sx={layoutSx}
             key={d.thumbSize.toString()}
        >
            <Masonry
                spacing={0}
            >
                {pages.map((page) => {
                    return page.map(item => {
                        const asset: Asset = itemToAsset ? itemToAsset(item) : (item as unknown as Asset);

                        return <AssetItem
                            key={item.id}
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
                    });
                })}
            </Masonry>
            <PreviewPopover
                key={previewAnchorEl?.asset.id ?? 'none'}
                asset={previewAnchorEl?.asset}
                anchorEl={previewAnchorEl?.anchorEl}
                displayAttributes={true}
            />
        </Box>
    );
}

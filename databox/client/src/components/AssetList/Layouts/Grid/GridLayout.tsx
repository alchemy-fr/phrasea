import React, {useContext} from 'react';
import {alpha, Grid, Theme} from '@mui/material';
import {LayoutProps} from "../../types.ts";
import {AssetOrAssetContainer} from "../../../../types.ts";
import {DisplayContext} from "../../../Media/DisplayContext.tsx";
import {sectionDividerClassname} from "../../../Media/Search/Layout/SectionDivider";
import assetClasses from "../../classes.ts";
import {createSizeTransition, thumbSx} from "../../../Media/Asset/Thumb";
import {createThumbActiveStyle} from "../../../Media/Asset/AssetThumb";
import GridPage from "./GridPage.tsx";
import PreviewPopover from "../../../Media/Asset/PreviewPopover.tsx";
import {usePreview} from "../../usePreview.ts";

export default function GridLayout<Item extends AssetOrAssetContainer>({
    toolbarHeight,
    pages,
    onToggle,
    onContextMenuOpen,
    onAddToBasket,
    onOpen,
    selection,
    itemToAsset,
}: LayoutProps<Item>) {
    const lineHeight = 26;
    const collLineHeight = 32;
    const tagLineHeight = 32;
    const d = useContext(DisplayContext)!;

    const gridSx = React.useCallback((theme: Theme) => {
        const spacing = Number(theme.spacing(1).slice(0, -2));

        const titleHeight = d.displayTitle
            ? spacing * 1.8 + lineHeight * d.titleRows
            : 0;
        let totalHeight = d.thumbSize + titleHeight;
        if (d.displayCollections) {
            totalHeight += collLineHeight * d.collectionsLimit;
        }
        if (d.displayTags) {
            totalHeight += tagLineHeight * d.tagsLimit;
        }

        return {
            p: 2,
            [`.${sectionDividerClassname}`]: {
                margin: `0 -${theme.spacing(1)}`,
                width: `calc(100% + ${theme.spacing(2)})`,
            },
            [`.${assetClasses.thumbWrapper}`]: {
                ...thumbSx(d.thumbSize)(theme),
                ...createThumbActiveStyle(),
            },
            [`.${assetClasses.item}`]: {
                'width': d.thumbSize,
                'height': totalHeight,
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
            [`.${assetClasses.title}`]: {
                fontSize: 14,
                p: 1,
                height: titleHeight,
                lineHeight: `${lineHeight}px`,
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                ...(d.titleRows > 1
                    ? {
                        'display': d.displayTitle
                            ? '-webkit-box'
                            : 'none',
                        '-webkit-line-clamp': `${d.titleRows}`,
                        '-webkit-box-orient': 'vertical',
                    }
                    : {
                        display: d.displayTitle ? 'block' : 'none',
                        whiteSpace: 'nowrap',
                    }),
            },
        };
    }, [d]);

    const {previewAnchorEl, onPreviewToggle} = usePreview([pages]);

    return (
        <>
            <Grid
                container
                spacing={1}
                sx={gridSx}
            >
                {pages.map((page, i) => <GridPage
                    key={i}
                    page={i + 1}
                    toolbarHeight={toolbarHeight}
                    items={page}
                    itemToAsset={itemToAsset}
                    onToggle={onToggle}
                    onPreviewToggle={onPreviewToggle}
                    onContextMenuOpen={onContextMenuOpen}
                    onAddToBasket={onAddToBasket}
                    onOpen={onOpen}
                    selection={selection}
                />)}
            </Grid>

            <PreviewPopover
                previewLocked={d.previewLocked}
                key={previewAnchorEl?.asset.id ?? 'none'}
                asset={previewAnchorEl?.asset}
                anchorEl={previewAnchorEl?.anchorEl}
                displayAttributes={true}
            />
        </>
    );
}

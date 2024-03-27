import {LayoutProps} from "../../types.ts";
import {AssetOrAssetContainer} from "../../../../types.ts";
import ListPage from "./ListPage.tsx";
import PreviewPopover from "../../PreviewPopover.tsx";
import {usePreview} from "../../usePreview.ts";
import {DisplayContext} from "../../../Media/DisplayContext.tsx";
import React from "react";
import assetClasses from "../../classes.ts";
import {thumbSx} from "../../../Media/Asset/Thumb.tsx";
import Box from "@mui/material/Box";
import {Theme} from "@mui/material";
import {attributesSx} from "../../../Media/Asset/Attribute/Attributes.tsx";
import {tagListSx} from "../../../Media/Asset/Widgets/AssetTagList.tsx";
import {collectionListSx} from "../../../Media/Asset/Widgets/AssetCollectionList.tsx";

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

    const layoutSx = React.useCallback((theme: Theme) => {
        return {
            ...tagListSx(),
            ...collectionListSx(),
            ...attributesSx(),
            ...thumbSx(d.thumbSize, theme),
            [`.${assetClasses.item}`]: {
                'p': 2,
                'position': 'relative',
                [`.${assetClasses.checkBtb}, .${assetClasses.controls}`]:
                    {
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
                    }
                },
            },
        };
    }, [d]);

    return (
        <Box
            sx={layoutSx}
        >
            {pages.map((page, i) => <ListPage
                key={i}
                page={i + 1}
                toolbarHeight={toolbarHeight}
                items={page}
                itemToAsset={itemToAsset}
                onToggle={onToggle}
                onPreviewToggle={onPreviewToggle}
                onContextMenuOpen={onContextMenuOpen}
                onAddToBasket={onAddToBasket}
                itemComponent={itemComponent}
                onOpen={onOpen}
                selection={selection}
                displayAttributes={d.displayAttributes}
            />)}

            <PreviewPopover
                key={previewAnchorEl?.asset.id ?? 'none'}
                asset={previewAnchorEl?.asset}
                anchorEl={previewAnchorEl?.anchorEl}
                displayAttributes={d.displayAttributes}
            />
        </Box>
    );
}

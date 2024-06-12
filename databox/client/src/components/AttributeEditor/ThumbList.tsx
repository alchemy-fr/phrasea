import {Asset} from "../../types.ts";
import React from "react";
import {DisplayContext} from "../Media/DisplayContext.tsx";
import AssetItem from "./AssetItem.tsx";
import {OnToggle} from "../AssetList/types.ts";
import {Box, Theme} from "@mui/material";
import {createSizeTransition, thumbSx} from "../Media/Asset/AssetThumb.tsx";
import assetClasses from "../AssetList/classes.ts";
import {scrollbarWidth} from "../../constants.ts";

type Props = {
    assets: Asset[];
    subSelection: Asset[];
    onToggle: OnToggle<Asset>;
};

export default function ThumbList({
    assets,
    subSelection,
    onToggle,
}: Props) {
    const d = React.useContext(DisplayContext)!;

    const selectedIds = React.useMemo(() => {
        return subSelection.map(a => a.id);
    }, [subSelection]);


    const listSx = React.useCallback(
        (theme: Theme) => {
            let totalHeight = d.thumbSize;

            return {
                overflow: "auto",
                height: d!.thumbSize + scrollbarWidth,
                display: 'flex',
                ...thumbSx(d.thumbSize, theme),
                px: 2,
                backgroundColor: theme.palette.common.white,
                [`.${assetClasses.item}`]: {
                    'width': d.thumbSize,
                    'height': totalHeight,
                    'transition': createSizeTransition(theme),
                    'position': 'relative',
                    opacity: 0.5,
                    [`.${assetClasses.controls}`]: {
                        'position': 'absolute',
                        'zIndex': 2,
                        'left': 0,
                        'top': 0,
                        'right': 0,
                        'padding': '1px',
                        '> div': {
                            float: 'right',
                        },
                        'background':
                            'linear-gradient(to bottom, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.5) 50%, rgba(255,255,255,0) 100%)',
                    },
                    '&.selected': {
                        opacity: 1
                    },
                },
                [`.${assetClasses.thumbActive}`]: {
                    display: 'none',
                },
            };
        },
        [d]
    );

    return <Box
        sx={listSx}
    >
        {assets.map(a => {
            return <AssetItem
                key={a.id}
                asset={a}
                onToggle={onToggle}
                selected={selectedIds.includes(a.id)}
                onPreviewToggle={() => {
                }}
            />
        })}
    </Box>
}

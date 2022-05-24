import GridLayout from "./Layout/GridLayout";
import ListLayout from "./Layout/ListLayout";
import React from "react";
import {Asset} from "../../../types";
import {Box} from "@mui/material";
import {OnSelectAsset, OnUnselectAsset, TOnContextMenuOpen} from "./Layout/Layout";

export const LAYOUT_GRID = 0;
export const LAYOUT_LIST = 1;

type Props = {
    pages: Asset[][];
    layout: number;
    selectedAssets: string[];
    onSelect: OnSelectAsset;
    onUnselect: OnUnselectAsset;
    onContextMenuOpen: TOnContextMenuOpen;
};

export default React.memo(function Pager({
                                             pages,
                                             layout,
                                             selectedAssets,
                                             onSelect,
                                             onUnselect,
                                             onContextMenuOpen,
                                         }: Props) {
    return <Box
        sx={{
            bgcolor: 'common.white',
        }}
    >
        {pages.map((assets, i) => {
            return <Box
                key={i}
                sx={(theme) => ({
                    position: 'relative',
                    width: '100%',
                    borderTop: `1px solid ${theme.palette.divider}`,
                    '&:first-of-type': {
                        borderTop: 0,
                    },
                    py: 2
                })}
            >
                {i > 0 && <Box sx={(theme) => ({
                    position: 'absolute',
                    top: -13,
                    left: 10,
                    color: theme.palette.divider,
                    fontWeight: 700,
                    fontSize: 15,
                    padding: `2px 10px`,
                    backgroundColor: theme.palette.common.white,
                })}># {i + 1}</Box>}
                {React.createElement(layout === LAYOUT_GRID ? GridLayout : ListLayout, {
                    assets,
                    onSelect,
                    onUnselect,
                    selectedAssets,
                    onContextMenuOpen,
                    page: i + 1,
                })}
            </Box>
        })}
    </Box>
})

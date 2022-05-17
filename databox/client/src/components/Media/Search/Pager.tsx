import GridLayout from "./Layout/GridLayout";
import ListLayout from "./Layout/ListLayout";
import React, {MouseEvent} from "react";
import {Asset} from "../../../types";
import {Box} from "@mui/material";
import {LayoutProps} from "./Layout/Layout";

export const LAYOUT_GRID = 0;
export const LAYOUT_LIST = 1;

type Props = {
    pages: Asset[][];
    layout: number;
    selectedAssets: string[];
    onSelect: (id: string, e: MouseEvent) => void;
    thumbSize: number;
};

export default React.memo(function Pager({
                                             pages,
                                             layout,
                                             thumbSize,
                                             selectedAssets,
                                             onSelect,
                                         }: Props) {
    return <div>
        {pages.map((assets, i) => {
            return <Box
                key={i}
                sx={{
                    position: 'relative',
                    width: '100%',
                    p: 3,
                    '&:first-of-type': {
                        borderTop: 0,
                        pt: 2,
                    }
                }}
            >
                {i > 0 && <Box sx={{
                    position: 'absolute',
                    top: -12,
                    left: 10,
                    fontWeight: 700,
                    padding: `2px 10px`,
                    backgroundColor: 'inherit',
                }}># {i + 1}</Box>}
                {React.createElement(layout === LAYOUT_GRID ? GridLayout : ListLayout, {
                    assets,
                    thumbSize,
                    onSelect,
                    selectedAssets,
                })}
            </Box>
        })}
    </div>
})

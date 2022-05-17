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
};

export default React.memo(function Pager({
                                             pages,
                                             layout,
                                             selectedAssets,
                                             onSelect,
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
                    p: 3,
                    borderTop: `1px solid ${theme.palette.divider}`,
                    '&:first-of-type': {
                        borderTop: 0,
                        pt: 2,
                    }
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
                    selectedAssets,
                })}
            </Box>
        })}
    </Box>
})

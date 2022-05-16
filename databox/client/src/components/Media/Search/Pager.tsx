import GridLayout from "./Layout/GridLayout";
import ListLayout from "./Layout/ListLayout";
import React, {MouseEvent} from "react";
import {Asset} from "../../../types";
import {Box} from "@mui/material";

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
    return <div>
        {pages.map((assets, i) => {
            return <Box
                key={i}
                sx={{
                    position: 'relative',
                    width: '100%',
                    p: 3,
                    '&:first-child': {
                        borderTop: 0,
                        '> div:first-child': {
                            display: 'none',
                        }
                    }
                }}
            >
                <Box sx={{
                    position: 'absolute',
                    top: -12,
                    left: 10,
                    fontWeight: 700,
                    padding: `2px 10px`,
                    backgroundColor: 'inherit',
                }}># {i + 1}</Box>
                {layout === LAYOUT_GRID ? <GridLayout
                    assets={assets}
                    onSelect={onSelect}
                    selectedAssets={selectedAssets}
                /> : <ListLayout
                    assets={assets}
                    onSelect={onSelect}
                    selectedAssets={selectedAssets}
                />}
            </Box>
        })}
    </div>
})

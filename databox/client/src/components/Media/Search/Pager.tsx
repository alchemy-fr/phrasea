import GridLayout from "./Layout/GridLayout";
import ListLayout from "./Layout/ListLayout";
import React, {MouseEvent} from "react";
import {Asset} from "../../../types";

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
            return <div
                key={i}
                className={'result-page'}
            >
                <div className="page-num"># {i + 1}</div>
                {layout === LAYOUT_GRID ? <GridLayout
                    assets={assets}
                    onSelect={onSelect}
                    selectedAssets={selectedAssets}
                /> : <ListLayout
                    assets={assets}
                    onSelect={onSelect}
                    selectedAssets={selectedAssets}
                />}
            </div>
        })}
    </div>
})

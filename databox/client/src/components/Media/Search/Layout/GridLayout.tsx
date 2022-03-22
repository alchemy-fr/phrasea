import React from "react";
import AssetItem from "../../Asset/AssetItem";
import {ImageList} from "@material-ui/core";
import {LayoutProps} from "./Layout";

const classes = {
    root: {},
    gridList: {
        width: '100%',
    },
};

export default function GridLayout({
                                      assets,
                                      selectedAssets,
                                      onSelect,
                                  }: LayoutProps) {
    return <ImageList rowHeight={180} style={classes.gridList}>
        {assets.map(a => <AssetItem
            {...a}
            displayAttributes={true}
            selected={selectedAssets.includes(a.id)}
            onClick={onSelect}
            key={a.id}
        />)}
    </ImageList>
}

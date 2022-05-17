import React, {useContext} from "react";
import {Box, Grid, Typography} from "@mui/material";
import {LayoutProps, OnSelectAsset, SelectedAssets} from "./Layout";
import AssetThumb from "../../Asset/AssetThumb";
import {Asset} from "../../../../types";
import {DisplayContext} from "../../DisplayContext";
import {createSizeTransition} from "../../Asset/Thumb";

function AssetItem({
                       asset,
                       selectedAssets,
                       onSelect,
                   }: {
    asset: Asset;
    onSelect: OnSelectAsset;
    selectedAssets: SelectedAssets;
}) {
    const {thumbSize} = useContext(DisplayContext);

    const isSelected = selectedAssets.includes(asset.id);
    return <Box
        onMouseDown={(e) => onSelect(asset.id, e)}
        sx={(theme) => ({
            backgroundColor: isSelected ? theme.palette.primary.main : 'transparent',
            color: isSelected ? theme.palette.primary.contrastText : undefined,
            boxShadow: isSelected ? theme.shadows[2] : 'none',
            width: thumbSize,
            height: thumbSize + 38,
            transition: createSizeTransition(theme),
        })}
    >
        <AssetThumb
            {...asset}
            thumbSize={thumbSize}
            displayAttributes={true}
            selected={selectedAssets.includes(asset.id)}
            onClick={onSelect}
        />
        <Box sx={{
            fontSize: 14,
            p: 1,
            overflow: 'hidden',
            textOverflow: 'ellipsis',
            whiteSpace: 'nowrap',
        }}>
            {asset.resolvedTitle}
        </Box>
    </Box>
}

export default function GridLayout({
                                       assets,
                                       selectedAssets,
                                       onSelect,
                                   }: LayoutProps) {

    return <Grid
        container
        spacing={1}
    >
        {assets.map(a => {

            return <Grid
                item
                key={a.id}
            >
                <AssetItem
                    asset={a}
                    selectedAssets={selectedAssets}
                    onSelect={onSelect}
                />
            </Grid>
        })}
    </Grid>
}

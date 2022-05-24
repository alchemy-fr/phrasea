import React, {useContext} from "react";
import {LayoutProps, OnSelectAsset, SelectedAssets, TOnContextMenuOpen} from "./Layout";
import {Box, Grid, IconButton} from "@mui/material";
import {alpha} from "@mui/material/styles";
import AssetThumb from "../../Asset/AssetThumb";
import {DisplayContext} from "../../DisplayContext";
import {Asset} from "../../../../types";
import SettingsIcon from "@mui/icons-material/Settings";
import Attributes from "../../Asset/Attribute/Attributes";
import assetClasses from "./classes";

function AssetItem({
                       asset,
                       selectedAssets,
                       onSelect,
                       onContextMenuOpen,
                       thumbSize,
                   }: {
    asset: Asset;
    onSelect: OnSelectAsset;
    selectedAssets: SelectedAssets;
    onContextMenuOpen: TOnContextMenuOpen;
    thumbSize: number;
}) {
    const isSelected = selectedAssets.includes(asset.id);

    return <div
        onMouseDown={(e) => onSelect(asset.id, e)}
        className={`${assetClasses.item} ${isSelected ? 'selected' : ''}`}
    >
        <Grid
            container
            spacing={2}
        >
            <Grid item>
                <IconButton
                    className={assetClasses.settingBtn}
                    onClick={(e) => onContextMenuOpen(e, asset)}
                >
                    <SettingsIcon
                        fontSize={'small'}
                        scale={0.45}
                    />
                </IconButton>
                <AssetThumb
                    {...asset}
                    thumbSize={thumbSize}
                    selected={selectedAssets.includes(asset.id)}
                    onClick={onSelect}
                />
            </Grid>
            <Grid item className={assetClasses.attributes}>
                <Attributes
                    asset={asset}
                />
            </Grid>
        </Grid>
    </div>
}


export default function ListLayout({
                                       assets,
                                       onSelect,
                                       selectedAssets,
                                       onContextMenuOpen,
                                   }: LayoutProps) {
    const {thumbSize} = useContext(DisplayContext)!;

    return <Box
        sx={(theme) => ({
            [`.${assetClasses.item}`]: {
                p: 2,
                position: 'relative',
                [`.${assetClasses.settingBtn}`]: {
                    position: 'absolute',
                    right: 1,
                    top: 1,
                    zIndex: 1,
                    opacity: 0,
                    transform: `translateY(-10px)`,
                    transition: theme.transitions.create(['opacity', 'transform'], {duration: 300}),
                },
                '&:hover, &.selected': {
                    [`.${assetClasses.settingBtn}`]: {
                        opacity: 1,
                        transform: `translateY(0)`,
                    },
                },
                '&.selected': {
                    backgroundColor: theme.palette.primary.main,
                    boxShadow: theme.shadows[2],
                    color: theme.palette.primary.contrastText,
                }
            },
        })}
    >
        {assets.map(a => <div
            onContextMenu={(e) => {
                onContextMenuOpen(e, a);
            }}
        >
            <AssetItem
                asset={a}
                selectedAssets={selectedAssets}
                onContextMenuOpen={onContextMenuOpen}
                onSelect={onSelect}
                thumbSize={thumbSize}
            />
        </div>)}
    </Box>
}

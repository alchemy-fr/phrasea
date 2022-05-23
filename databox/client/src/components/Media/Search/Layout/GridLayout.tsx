import React, {useContext} from "react";
import {Box, Grid, IconButton, useTheme} from "@mui/material";
import {LayoutProps, OnSelectAsset, SelectedAssets, TOnContextMenuOpen} from "./Layout";
import AssetThumb from "../../Asset/AssetThumb";
import {Asset} from "../../../../types";
import {DisplayContext} from "../../DisplayContext";
import {createSizeTransition} from "../../Asset/Thumb";
import SettingsIcon from '@mui/icons-material/Settings';

const lineHeight = 26;

function AssetItem({
                       asset,
                       selectedAssets,
                       onSelect,
                       onContextMenuOpen,
                   }: {
    asset: Asset;
    onSelect: OnSelectAsset;
    selectedAssets: SelectedAssets;
    onContextMenuOpen: TOnContextMenuOpen;
}) {
    const theme = useTheme();
    const {thumbSize, displayTitle, titleRows} = useContext(DisplayContext)!;
    const spacing = Number(theme.spacing(1).slice(0, -2));
    const titleHeight = displayTitle ? spacing * 1.8 + lineHeight * titleRows : 0;
    const isSelected = selectedAssets.includes(asset.id);

    return <Box
        onMouseDown={(e) => onSelect(asset.id, e)}
        className={isSelected ? 'selected' : undefined}
        sx={(theme) => ({
            backgroundColor: isSelected ? theme.palette.primary.main : 'transparent',
            color: isSelected ? theme.palette.primary.contrastText : undefined,
            boxShadow: isSelected ? theme.shadows[2] : 'none',
            width: thumbSize,
            height: thumbSize + titleHeight,
            transition: createSizeTransition(theme),
            position: 'relative',
            '.asset-settings': {
                opacity: 0,
                transform: `translateY(-10px)`,
                transition: theme.transitions.create(['opacity', 'transform'], {duration: 300}),
            },
            '&:hover, &.selected': {
                '.asset-settings': {
                    opacity: 1,
                    transform: `translateY(0)`,
                },
            },
        })}
    >
        <IconButton
            className={'asset-settings'}
            sx={{
                position: 'absolute',
                right: 1,
                top: 1,
            }}
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
            displayAttributes={true}
            selected={selectedAssets.includes(asset.id)}
            onClick={onSelect}
        />
        {displayTitle && <Box sx={{
            fontSize: 14,
            p: 1,
            height: titleHeight,
            lineHeight: `${lineHeight}px`,
            overflow: 'hidden',
            textOverflow: 'ellipsis',
            ...(titleRows > 1 ? {
                display: '-webkit-box',
                '-webkit-line-clamp': `${titleRows}`,
                '-webkit-box-orient': 'vertical',
            } : {
                whiteSpace: 'nowrap'
            }),
        }}>
            {asset.resolvedTitle}
        </Box>}
    </Box>
}

export default function GridLayout({
                                       assets,
                                       selectedAssets,
                                       onSelect,
                                       onContextMenuOpen,
                                   }: LayoutProps) {

    return <Grid
        container
        spacing={1}
    >
        {assets.map(a => {

            return <Grid
                item
                key={a.id}
                onContextMenu={(e) => {
                    onContextMenuOpen(e, a);
                }}
            >
                <AssetItem
                    asset={a}
                    selectedAssets={selectedAssets}
                    onContextMenuOpen={onContextMenuOpen}
                    onSelect={onSelect}
                />
            </Grid>
        })}
    </Grid>
}

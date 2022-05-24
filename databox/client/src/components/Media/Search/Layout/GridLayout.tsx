import React, {useContext} from "react";
import {Grid, IconButton, useTheme} from "@mui/material";
import {LayoutProps, OnSelectAsset, OnUnselectAsset, SelectedAssets, TOnContextMenuOpen} from "./Layout";
import AssetThumb from "../../Asset/AssetThumb";
import {Asset} from "../../../../types";
import {DisplayContext} from "../../DisplayContext";
import {createSizeTransition} from "../../Asset/Thumb";
import SettingsIcon from '@mui/icons-material/Settings';
import CheckBoxIcon from '@mui/icons-material/CheckBox';
import assetClasses from "./classes";
import {stopPropagation} from "../../../../lib/stdFuncs";

const lineHeight = 26;

function AssetItem({
                       asset,
                       selectedAssets,
                       onSelect,
                       onUnselect,
                       onContextMenuOpen,
                       thumbSize,
                   }: {
    asset: Asset;
    onSelect: OnSelectAsset;
    onUnselect: OnUnselectAsset;
    selectedAssets: SelectedAssets;
    onContextMenuOpen: TOnContextMenuOpen;
    thumbSize: number;
}) {
    const isSelected = selectedAssets.includes(asset.id);

    return <div
        onMouseDown={(e) => onSelect(asset.id, e)}
        className={`${assetClasses.item} ${isSelected ? 'selected' : ''}`}
    >
        {isSelected ? <IconButton
            className={assetClasses.settingBtn}
            onMouseDown={stopPropagation}
            onClick={e => onUnselect(asset.id, e)}
        >
            <CheckBoxIcon
                fontSize={'small'}
                color={'success'}
            />
        </IconButton> : <IconButton
            className={assetClasses.settingBtn}
            onMouseDown={stopPropagation}
            onClick={(e) => onContextMenuOpen(e, asset)}
        >
            <SettingsIcon
                fontSize={'small'}
            />
        </IconButton>}
        <AssetThumb
            {...asset}
            thumbSize={thumbSize}
            selected={selectedAssets.includes(asset.id)}
            onClick={onSelect}
        />
        <div className={assetClasses.title}>
            {asset.resolvedTitle}
        </div>
    </div>
}

export default function GridLayout({
                                       assets,
                                       selectedAssets,
                                       onSelect,
                                       onUnselect,
                                       onContextMenuOpen,
                                   }: LayoutProps) {
    const theme = useTheme();
    const {thumbSize, displayTitle, titleRows} = useContext(DisplayContext)!;
    const spacing = Number(theme.spacing(1).slice(0, -2));
    const titleHeight = displayTitle ? spacing * 1.8 + lineHeight * titleRows : 0;

    return <Grid
        container
        spacing={1}
        sx={(theme) => ({
            p: 2,
            [`.${assetClasses.item}`]: {
                width: thumbSize,
                height: thumbSize + titleHeight,
                transition: createSizeTransition(theme),
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
            [`.${assetClasses.title}`]: {
                fontSize: 14,
                p: 1,
                height: titleHeight,
                lineHeight: `${lineHeight}px`,
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                ...(titleRows > 1 ? {
                    display: displayTitle ? '-webkit-box' : 'none',
                    '-webkit-line-clamp': `${titleRows}`,
                    '-webkit-box-orient': 'vertical',
                } : {
                    display: displayTitle ? 'block' : 'none',
                    whiteSpace: 'nowrap'
                }),
            }
        })}
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
                    onUnselect={onUnselect}
                    thumbSize={thumbSize}
                />
            </Grid>
        })}
    </Grid>
}

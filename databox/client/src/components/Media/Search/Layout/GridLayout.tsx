import React, {MouseEvent, useContext} from "react";
import {alpha, Checkbox, Grid, IconButton, useTheme} from "@mui/material";
import {
    LayoutProps,
    OnPreviewToggle,
    OnSelectAsset,
    OnUnselectAsset,
    TOnContextMenuOpen
} from "./Layout";
import AssetThumb, {createThumbActiveStyle} from "../../Asset/AssetThumb";
import {Asset} from "../../../../types";
import {DisplayContext} from "../../DisplayContext";
import {createSizeTransition} from "../../Asset/Thumb";
import SettingsIcon from '@mui/icons-material/Settings';
import assetClasses from "./classes";
import {stopPropagation} from "../../../../lib/stdFuncs";
import AssetCollectionList from "../../Asset/Widgets/CollectionList";
import AssetTagList from "../../Asset/Widgets/AssetTagList";

const lineHeight = 26;
const collLineHeight = 32;
const tagLineHeight = 32;

const AssetItem = React.memo(({
                       asset,
                          selected,
                       onSelect,
                       onUnselect,
                       onContextMenuOpen,
                       thumbSize,
                       onPreviewToggle,
                   }: {
    asset: Asset;
    onSelect: OnSelectAsset;
    onUnselect: OnUnselectAsset;
    onPreviewToggle: OnPreviewToggle;
    selected: boolean;
    onContextMenuOpen: TOnContextMenuOpen;
    thumbSize: number;
}) => {
    return <div
        onMouseDown={(e) => onSelect(asset.id, e)}
        className={`${assetClasses.item} ${selected ? 'selected' : ''}`}
    >
        <Checkbox
            className={assetClasses.checkBtb}
            checked={selected}
            color={'primary'}
            onMouseDown={stopPropagation}
            onChange={e => (e.target.checked ? onSelect : onUnselect)(asset.id, {
                ctrlKey: true,
                preventDefault() {
                }
            } as MouseEvent)}
        />
        <IconButton
            className={assetClasses.settingBtn}
            onMouseDown={stopPropagation}
            onClick={function (e) {
                onContextMenuOpen(e, asset, e.currentTarget);
            }}
        >
            <SettingsIcon
                fontSize={'small'}
            />
        </IconButton>
        <AssetThumb
            asset={asset}
            onMouseOver={(e) => onPreviewToggle(asset, true, e.currentTarget as HTMLElement)}
            onMouseLeave={(e) => onPreviewToggle(asset, false, e.currentTarget as HTMLElement)}
            thumbSize={thumbSize}
            selected={selected}
        />
        <div className={assetClasses.legend}>
            <div className={assetClasses.title}>
                {asset.resolvedTitle}
            </div>
            {asset.tags.length > 0 && <div>
                <AssetTagList
                    tags={asset.tags}
                />
            </div>}
            {asset.collections.length > 0 && <div>
                <AssetCollectionList
                    collections={asset.collections}
                />
            </div>}
        </div>
    </div>
});

export default function GridLayout({
                                       assets,
                                       selectedAssets,
                                       onSelect,
                                       onUnselect,
                                       onPreviewToggle,
                                       onContextMenuOpen,
                                   }: LayoutProps) {
    const theme = useTheme();
    const d = useContext(DisplayContext)!;
    const spacing = Number(theme.spacing(1).slice(0, -2));

    const titleHeight = d.displayTitle ? spacing * 1.8 + lineHeight * d.titleRows : 0;
    let totalHeight = d.thumbSize + titleHeight;
    if (d.displayCollections) {
        totalHeight += collLineHeight * d.collectionsLimit;
    }
    if (d.displayTags) {
        totalHeight += tagLineHeight * d.tagsLimit;
    }

    return <Grid
        container
        spacing={1}
        sx={(theme) => ({
            p: 2,
            [`.${assetClasses.item}`]: {
                width: d.thumbSize,
                height: totalHeight,
                transition: createSizeTransition(theme),
                position: 'relative',
                [`.${assetClasses.checkBtb}, .${assetClasses.settingBtn}`]: {
                    position: 'absolute',
                    zIndex: 2,
                    opacity: 0,
                    transform: `translateY(-10px)`,
                    transition: theme.transitions.create(['opacity', 'transform'], {duration: 300}),
                },
                [`.${assetClasses.checkBtb}`]: {
                    left: 1,
                    top: 1,
                },
                [`.${assetClasses.settingBtn}`]: {
                    right: 1,
                    top: 1,
                },
                ...createThumbActiveStyle(),
                '&:hover, &.selected': {
                    [`.${assetClasses.checkBtb}, .${assetClasses.settingBtn}`]: {
                        opacity: 1,
                        transform: `translateY(0)`,
                    },
                },
                '&.selected': {
                    backgroundColor: alpha(theme.palette.primary.main, 0.8),
                    boxShadow: theme.shadows[2],
                    [`.${assetClasses.legend}`]: {
                        color: theme.palette.primary.contrastText,
                    }
                }
            },
            [`.${assetClasses.thumbActive}`]: {
                display: 'none',
            },
            [`.${assetClasses.title}`]: {
                fontSize: 14,
                p: 1,
                height: titleHeight,
                lineHeight: `${lineHeight}px`,
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                ...(d.titleRows > 1 ? {
                    display: d.displayTitle ? '-webkit-box' : 'none',
                    '-webkit-line-clamp': `${d.titleRows}`,
                    '-webkit-box-orient': 'vertical',
                } : {
                    display: d.displayTitle ? 'block' : 'none',
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
                    selected={selectedAssets.includes(a.id)}
                    onContextMenuOpen={onContextMenuOpen}
                    onSelect={onSelect}
                    onPreviewToggle={onPreviewToggle}
                    onUnselect={onUnselect}
                    thumbSize={d.thumbSize}
                />
            </Grid>
        })}
    </Grid>
}

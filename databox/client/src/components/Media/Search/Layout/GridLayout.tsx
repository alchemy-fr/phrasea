import React, {MouseEvent, useContext} from "react";
import {alpha, Checkbox, Grid, IconButton, useTheme} from "@mui/material";
import {LayoutProps, OnPreviewToggle, OnSelectAsset, OnUnselectAsset, TOnContextMenuOpen} from "./Layout";
import AssetThumb, {createThumbActiveStyle} from "../../Asset/AssetThumb";
import {Asset} from "../../../../types";
import {DisplayContext} from "../../DisplayContext";
import {createSizeTransition} from "../../Asset/Thumb";
import SettingsIcon from '@mui/icons-material/Settings';
import assetClasses from "./classes";
import {stopPropagation} from "../../../../lib/stdFuncs";
import AssetCollectionList from "../../Asset/Widgets/CollectionList";
import AssetTagList from "../../Asset/Widgets/AssetTagList";
import {PrivacyTooltip} from "../../../Ui/PrivacyChip";
import {replaceHighlight} from "../../Asset/Attribute/Attributes";
import {hasContextMenu} from "../../Asset/AssetContextMenu";

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
    onPreviewToggle?: OnPreviewToggle;
    selected: boolean;
    onContextMenuOpen?: TOnContextMenuOpen;
    thumbSize: number;
}) => {
    return <div
        onMouseDown={(e) => onSelect(asset.id, e)}
        className={`${assetClasses.item} ${selected ? 'selected' : ''}`}
    >
        <div className={assetClasses.controls}>
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
            <PrivacyTooltip
                iconProps={{
                    fontSize: 'small'
                }}
                tooltipProps={{
                    sx: {
                        display: 'inline-block',
                        verticalAlign: 'middle',
                        mr: 1,
                    }
                }}
                privacy={asset.privacy}
            />
            {onContextMenuOpen && <IconButton
                className={assetClasses.settingBtn}
                onMouseDown={stopPropagation}
                onClick={function (e) {
                    onContextMenuOpen(e, asset, e.currentTarget);
                }}
            >
                <SettingsIcon
                    fontSize={'small'}
                />
            </IconButton>}
        </div>
        <AssetThumb
            asset={asset}
            onMouseOver={onPreviewToggle ? (e) => onPreviewToggle(asset, true, e.currentTarget as HTMLElement) : undefined}
            onMouseLeave={onPreviewToggle ? (e) => onPreviewToggle(asset, false, e.currentTarget as HTMLElement) : undefined}
            thumbSize={thumbSize}
            selected={selected}
        />
        <div className={assetClasses.legend}>
            <div className={assetClasses.title}>
                {asset.titleHighlight ? replaceHighlight(asset.titleHighlight) : asset.title}
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
                [`.${assetClasses.controls}`]: {
                    position: 'absolute',
                    transform: `translateY(-10px)`,
                    zIndex: 2,
                    opacity: 0,
                    left: 0,
                    top: 0,
                    right: 0,
                    padding: '1px',
                    transition: theme.transitions.create(['opacity', 'transform'], {duration: 300}),
                    background:
                        'linear-gradient(to bottom, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.5) 50%, rgba(255,255,255,0) 100%)',
                },
                [`.${assetClasses.settingBtn}`]: {
                    position: 'absolute',
                    right: 1,
                    top: 1,
                },
                ...createThumbActiveStyle(),
                '&:hover, &.selected': {
                    [`.${assetClasses.controls}`]: {
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
            const contextMenu = onContextMenuOpen && hasContextMenu(a);

            return <Grid
                item
                key={a.id}
                onContextMenu={onContextMenuOpen ? (e) => {
                    if (!contextMenu) {
                        e.preventDefault();
                        return;
                    }
                    onContextMenuOpen!(e, a);
                } : undefined}
            >
                <AssetItem
                    asset={a}
                    selected={selectedAssets.includes(a.id)}
                    onContextMenuOpen={contextMenu ? onContextMenuOpen : undefined}
                    onSelect={onSelect}
                    onPreviewToggle={onPreviewToggle}
                    onUnselect={onUnselect}
                    thumbSize={d.thumbSize}
                />
            </Grid>
        })}
    </Grid>
}

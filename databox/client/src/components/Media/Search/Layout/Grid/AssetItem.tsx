// @ts-nocheck
import React, {MouseEvent} from "react";
import {Asset} from "../../../../../types.ts";
import {OnAddToBasket, OnPreviewToggle, OnSelectAsset, OnUnselectAsset, TOnContextMenuOpen} from "../Layout.ts";
import assetClasses from "../../../../AssetList/classes.ts";
import {Checkbox, IconButton} from "@mui/material";
import {stopPropagation} from "../../../../../lib/stdFuncs.ts";
import {PrivacyTooltip} from "../../../../Ui/PrivacyChip.tsx";
import ShoppingCartIcon from "@mui/icons-material/ShoppingCart";
import SettingsIcon from "@mui/icons-material/Settings";
import AssetThumb from "../../../Asset/AssetThumb.tsx";
import {replaceHighlight} from "../../../Asset/Attribute/Attributes.tsx";
import AssetTagList from "../../../Asset/Widgets/AssetTagList.tsx";
import AssetCollectionList from "../../../Asset/Widgets/AssetCollectionList.tsx";

export const AssetItem = React.memo(
    ({
        asset,
        selected,
        onSelect,
        onUnselect,
        onContextMenuOpen,
        thumbSize,
        onPreviewToggle,
        onAddToBasket,
    }: {
        asset: Asset;
        onAddToBasket?: OnAddToBasket;
        onSelect: OnSelectAsset;
        onUnselect: OnUnselectAsset;
        onPreviewToggle?: OnPreviewToggle;
        selected: boolean;
        onContextMenuOpen?: TOnContextMenuOpen;
        thumbSize: number;
    }) => {
        return (
            <div
                onMouseDown={e => onSelect(asset.id, e)}
                className={`${assetClasses.item} ${selected ? 'selected' : ''}`}
            >
                <div className={assetClasses.controls}>
                    <Checkbox
                        className={assetClasses.checkBtb}
                        checked={selected}
                        color={'primary'}
                        onMouseDown={stopPropagation}
                        onChange={e =>
                            (e.target.checked ? onSelect : onUnselect)(
                                asset.id,
                                {
                                    ctrlKey: true,
                                    preventDefault() {},
                                } as MouseEvent
                            )
                        }
                    />
                    <PrivacyTooltip
                        iconProps={{
                            fontSize: 'small',
                        }}
                        tooltipProps={{
                            sx: {
                                display: 'inline-block',
                                verticalAlign: 'middle',
                                mr: 1,
                            },
                        }}
                        privacy={asset.privacy}
                    />
                    {onAddToBasket ? <IconButton
                        className={assetClasses.cartBtn}
                        onMouseDown={stopPropagation}
                        onDoubleClick={stopPropagation}
                        onClick={(e) => onAddToBasket(asset.id, e)}
                    >
                        <ShoppingCartIcon fontSize={'small'} />
                    </IconButton> : null}
                    {onContextMenuOpen && (
                        <IconButton
                            className={assetClasses.settingBtn}
                            onMouseDown={stopPropagation}
                            onDoubleClick={stopPropagation}
                            onClick={function (e) {
                                onContextMenuOpen(e, asset, e.currentTarget);
                            }}
                        >
                            <SettingsIcon fontSize={'small'} />
                        </IconButton>
                    )}
                </div>
                <AssetThumb
                    asset={asset}
                    onMouseOver={
                        onPreviewToggle
                            ? e =>
                                onPreviewToggle(
                                    asset,
                                    true,
                                    e.currentTarget as HTMLElement
                                )
                            : undefined
                    }
                    onMouseLeave={
                        onPreviewToggle
                            ? e =>
                                onPreviewToggle(
                                    asset,
                                    false,
                                    e.currentTarget as HTMLElement
                                )
                            : undefined
                    }
                    thumbSize={thumbSize}
                    selected={selected}
                />
                <div className={assetClasses.legend}>
                    <div className={assetClasses.title}>
                        {asset.titleHighlight
                            ? replaceHighlight(asset.titleHighlight)
                            : asset.resolvedTitle ?? asset.title}
                    </div>
                    {asset.tags.length > 0 && (
                        <div>
                            <AssetTagList tags={asset.tags} />
                        </div>
                    )}
                    {asset.collections.length > 0 && (
                        <div>
                            <AssetCollectionList
                                collections={asset.collections}
                            />
                        </div>
                    )}
                </div>
            </div>
        );
    }
);

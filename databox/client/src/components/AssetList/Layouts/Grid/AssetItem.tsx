import React from "react";
import {AssetOrAssetContainer} from "../../../../types.ts";
import assetClasses from "../../../Media/Search/Layout/classes.ts";
import {PrivacyTooltip} from "../../../Ui/PrivacyChip.tsx";
import IconButton from "@mui/material/IconButton";
import ShoppingCartIcon from "@mui/icons-material/ShoppingCart";
import SettingsIcon from "@mui/icons-material/Settings";
import AssetThumb from "../../../Media/Asset/AssetThumb.tsx";
import {replaceHighlight} from "../../../Media/Asset/Attribute/Attributes.tsx";
import AssetTagList from "../../../Media/Asset/Widgets/AssetTagList.tsx";
import AssetCollectionList from "../../../Media/Asset/Widgets/AssetCollectionList.tsx";
import {AssetItemProps} from "../../types.ts";
import {Checkbox} from "@mui/material";
import {stopPropagation} from "../../../../lib/stdFuncs.ts";

export default function AssetItem<Item extends AssetOrAssetContainer>({
    item,
    asset,
    selected,
    onToggle,
    onContextMenuOpen,
    onPreviewToggle,
    onAddToBasket,
}: AssetItemProps<Item>) {
    return (
        <div
            onMouseDown={e => onToggle(item, e)}
            className={`${assetClasses.item} ${selected ? 'selected' : ''}`}
        >
            <div className={assetClasses.controls}>
                <Checkbox
                    className={assetClasses.checkBtb}
                    checked={selected}
                    color={'primary'}
                    onMouseDown={stopPropagation}
                    onChange={() => onToggle(item, {
                        ctrlKey: true,
                        preventDefault() {
                        },
                    } as React.MouseEvent)}
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
                    onClick={(e) => onAddToBasket(asset, e)}
                >
                    <ShoppingCartIcon fontSize={'small'}/>
                </IconButton> : null}
                {onContextMenuOpen && (
                    <IconButton
                        className={assetClasses.settingBtn}
                        onMouseDown={stopPropagation}
                        onDoubleClick={stopPropagation}
                        onClick={function (e) {
                            onContextMenuOpen(e, item, e.currentTarget);
                        }}
                    >
                        <SettingsIcon fontSize={'small'}/>
                    </IconButton>
                )}
            </div>
            <AssetThumb
                asset={asset}
                onMouseOver={
                    onPreviewToggle
                        ? e =>
                            onPreviewToggle(
                                item,
                                true,
                                e.currentTarget as HTMLElement
                            )
                        : undefined
                }
                onMouseLeave={
                    onPreviewToggle
                        ? e =>
                            onPreviewToggle(
                                item,
                                false,
                                e.currentTarget as HTMLElement
                            )
                        : undefined
                }
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
                        <AssetTagList tags={asset.tags}/>
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

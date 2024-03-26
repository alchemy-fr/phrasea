import React from "react";
import {AssetOrAssetContainer} from "../../../../types.ts";
import assetClasses from "../../classes.ts";
import IconButton from "@mui/material/IconButton";
import ShoppingCartIcon from "@mui/icons-material/ShoppingCart";
import SettingsIcon from "@mui/icons-material/Settings";
import AssetThumb from "../../../Media/Asset/AssetThumb.tsx";
import {AssetItemProps, OnPreviewToggle} from "../../types.ts";
import {Checkbox} from "@mui/material";
import {stopPropagation} from "../../../../lib/stdFuncs.ts";
import AssetItemWrapper from "../AssetItemWrapper.tsx";

type Props<Item extends AssetOrAssetContainer> = {
    onPreviewToggle?: OnPreviewToggle;
} & AssetItemProps<Item>;

export default function AssetItem<Item extends AssetOrAssetContainer>({
    item,
    asset,
    selected,
    onToggle,
    onContextMenuOpen,
    onPreviewToggle,
    onAddToBasket,
    itemComponent,
}: Props<Item>) {
    const disabled = !asset.workspace;

    return (
        <AssetItemWrapper
            item={item}
            itemComponent={itemComponent}
            onToggle={onToggle}
            selected={selected}
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

                {!disabled ? <>
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
                </> : ''}
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
            />
        </AssetItemWrapper>
    );
}

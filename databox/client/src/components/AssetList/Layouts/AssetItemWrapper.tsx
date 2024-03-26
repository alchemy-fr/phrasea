import {AssetItemProps} from "../types.ts";
import {AssetOrAssetContainer} from "../../../types.ts";
import assetClasses from "../classes.ts";
import React, {PropsWithChildren} from "react";

export default function AssetItemWrapper<Item extends AssetOrAssetContainer>({
    itemComponent,
    onToggle,
    item,
    children,
    selected,
}: PropsWithChildren<Pick<AssetItemProps<Item>, "item" | "itemComponent" | "onToggle" | "selected">>) {
    const node = <div
        onMouseDown={e => onToggle(item, e)}
        className={`${assetClasses.item} ${selected ? 'selected' : ''}`}
    >
        {children}
    </div>

    return itemComponent ? React.createElement(itemComponent, {
        item,
        children: node,
    }) : node;
}

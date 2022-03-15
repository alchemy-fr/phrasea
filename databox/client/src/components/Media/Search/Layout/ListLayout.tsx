import React from "react";
import AssetItem from "../../Asset/AssetItem";
import Attributes from "../../Asset/Attributes";
import {LayoutProps} from "./Layout";

export default function ListLayout({
                                       assets,
                                       onSelect,
                                       selectedAssets,
                                   }: LayoutProps) {
    return <div>
        {assets.map(a => <div className={'asset-list'}>
            <AssetItem
                {...a}
                displayAttributes={false}
                selected={selectedAssets.includes(a.id)}
                onClick={onSelect}
                key={a.id}
            />
            <Attributes asset={a}/>
        </div>)}
    </div>
}

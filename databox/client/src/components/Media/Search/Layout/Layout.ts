import {Asset} from "../../../../types";
import {MouseEvent} from "react";

export type OnSelectAsset = (id: string, e: MouseEvent) => void;
export type SelectedAssets = string[];

export type LayoutProps = {
    assets: Asset[];
    onSelect: OnSelectAsset;
    selectedAssets: SelectedAssets;
    thumbSize: number;
}

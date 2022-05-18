import {Asset} from "../../../../types";
import {MouseEvent} from "react";

export type OnSelectAsset = (id: string, e: MouseEvent) => void;
export type SelectedAssets = string[];

export type TOnContextMenuOpen = (e: MouseEvent<HTMLElement>, asset: Asset) => void;

export type LayoutProps = {
    assets: Asset[];
    onSelect: OnSelectAsset;
    selectedAssets: SelectedAssets;
    onContextMenuOpen: TOnContextMenuOpen;
}

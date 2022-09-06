import {Asset} from "../../../../types";
import {MouseEvent} from "react";

export type OnSelectAsset = (id: string, e?: MouseEvent) => void;
export type OnOpen = (asset: Asset) => void;
export type OnUnselectAsset = (id: string, e?: MouseEvent) => void;
export type OnPreviewToggle = (asset: Asset, display: boolean, anchorEl: HTMLElement) => void;
export type SelectedAssets = string[];

export type TOnContextMenuOpen = (e: MouseEvent<HTMLElement>, asset: Asset, anchorEl?: HTMLElement) => void;

export type LayoutProps = {
    page: number;
    assets: Asset[];
    onSelect: OnSelectAsset;
    onOpen?: OnOpen;
    onUnselect: OnUnselectAsset;
    onPreviewToggle?: OnPreviewToggle;
    selectedAssets: SelectedAssets;
    onContextMenuOpen?: TOnContextMenuOpen;
}

import {MouseEvent} from "react";
import {Asset, AssetOrAssetContainer} from "../../types.ts";

export type ItemToAssetFunc<Item extends AssetOrAssetContainer> = (item: Item) => Asset;
export type OnAddToBasket = (asset: Asset, e?: MouseEvent) => void;
export type OnOpen = (asset: Asset, renditionId: string) => void;
export type OnToggle<Item extends AssetOrAssetContainer> = (item: Item, e?: MouseEvent) => void;
export type OnPreviewToggle<Item extends AssetOrAssetContainer> = (
    item: Item,
    display: boolean,
    anchorEl: HTMLElement
) => void;

export type TOnContextMenuOpen<Item extends AssetOrAssetContainer> = (
    e: MouseEvent<HTMLElement>,
    item: Item,
    anchorEl?: HTMLElement
) => void;

export type AssetActions<Item extends AssetOrAssetContainer> = {
    onAddToBasket?: OnAddToBasket;
    onOpen?: OnOpen;
    onToggle: OnToggle<Item>;
    onPreviewToggle?: OnPreviewToggle<Item>;
    onContextMenuOpen?: TOnContextMenuOpen<Item>;
}

export type AssetItemProps<Item extends AssetOrAssetContainer> = {
    item: Item;
    asset: Asset;
    selected: boolean;
} & AssetActions<Item>;

type LayoutCommonProps<Item extends AssetOrAssetContainer>  = {
    itemToAsset?: ItemToAssetFunc<Item> | undefined;
    selection: Item[];
    searchMenuHeight: number;
}

export type LayoutProps<Item extends AssetOrAssetContainer> = {
    pages: Item[][];
} & LayoutCommonProps<Item> & AssetActions<Item>;

export type LayoutPageProps<Item extends AssetOrAssetContainer> = {
    items: Item[];
    page: number;
} & LayoutCommonProps<Item> & AssetActions<Item>;

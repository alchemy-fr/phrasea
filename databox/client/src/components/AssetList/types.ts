import React, {MouseEvent, PropsWithChildren} from 'react';
import {Asset, AssetOrAssetContainer} from '../../types';
import {ButtonProps} from '@mui/material/Button';

export type ItemToAssetFunc<Item extends AssetOrAssetContainer> = (
    item: Item
) => Asset;
export type OnAddToBasket = (asset: Asset, e?: MouseEvent) => void;
export type OnSelectionChange<Item extends AssetOrAssetContainer> = (
    items: Item[]
) => void;
export type OnOpen = (asset: Asset, renditionId: string) => void;
export type OnToggle<Item extends AssetOrAssetContainer> = (
    item: Item,
    e?: MouseEvent
) => void;
export type OnPreviewToggle = (
    asset: Asset,
    display: boolean,
    anchorEl: HTMLElement
) => void;

export type ReloadFunc = () => Promise<any>;
export type LoadMoreFunc = () => Promise<any>;

export type OnContextMenuOpen<Item extends AssetOrAssetContainer> = (
    e: MouseEvent<HTMLElement>,
    item: Item,
    anchorEl?: HTMLElement
) => void;

export type AssetActions<Item extends AssetOrAssetContainer> = {
    onAddToBasket?: OnAddToBasket;
    onOpen?: OnOpen;
    onToggle: OnToggle<Item>;
    onContextMenuOpen?: OnContextMenuOpen<Item>;
};

export type AssetItemProps<Item extends AssetOrAssetContainer> = {
    itemComponent: AssetItemComponent<Item> | undefined;
    item: Item;
    asset: Asset;
    selected: boolean;
} & AssetActions<Item>;

export type AssetItemCustomComponentProps<Item extends AssetOrAssetContainer> =
    PropsWithChildren<{
        item: Item;
    }>;
export type AssetItemComponent<Item extends AssetOrAssetContainer> = React.FC<
    AssetItemCustomComponentProps<Item>
>;

type LayoutCommonProps<Item extends AssetOrAssetContainer> = {
    itemToAsset?: ItemToAssetFunc<Item> | undefined;
    itemComponent: AssetItemComponent<Item> | undefined;
    selection: Item[];
    toolbarHeight: number;
};

export type LayoutProps<Item extends AssetOrAssetContainer> = {
    pages: Item[][];
} & LayoutCommonProps<Item> &
    AssetActions<Item>;

export type LayoutPageProps<Item extends AssetOrAssetContainer> = {
    items: Item[];
    page: number;
} & LayoutCommonProps<Item> &
    AssetActions<Item>;

export type CustomItemAction<Item extends AssetOrAssetContainer> = {
    name: string;
    buttonProps?: ButtonProps;
    labels: {
        single: string;
        multi: string;
    };
    apply: (items: Item[]) => Promise<void>;
    reload?: boolean;
    resetSelection?: boolean;
    disabled?: boolean;
};

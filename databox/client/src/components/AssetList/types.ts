import React, {MouseEvent, PropsWithChildren, ReactNode} from 'react';
import {Asset, AssetOrAssetContainer} from '../../types';
import {ButtonProps} from '@mui/material/Button';

export type ItemToAssetFunc<Item extends AssetOrAssetContainer> = (
    item: Item
) => Asset;
export type OnAddToBasket = (asset: Asset, e?: MouseEvent) => void;
export type OnSelectionChange<Item extends AssetOrAssetContainer> = (
    items: Item[]
) => void;

export type OnOpen = (
    asset: Asset,
    renditionId?: string,
    storyAssetId?: string
) => void;

export type OnToggle<Item extends AssetOrAssetContainer> = (
    item: Item,
    e?: MouseEvent
) => void;

export type OnPreviewToggle = (props: {
    asset: Asset;
    display: boolean;
    anchorEl?: HTMLElement;
    lock?: boolean;
}) => void;

export type ReloadFunc = () => Promise<any>;
export type LoadMoreFunc = () => Promise<any>;

export type OnAssetContextMenuOpen<Item extends AssetOrAssetContainer> = (
    e: MouseEvent<HTMLElement>,
    item: Item,
    anchorEl?: HTMLElement
) => void;

export type AssetActions<Item extends AssetOrAssetContainer> = {
    onAddToBasket?: OnAddToBasket;
    onOpen?: OnOpen;
    onToggle: OnToggle<Item>;
    onContextMenuOpen?: OnAssetContextMenuOpen<Item>;
};

export type AssetItemProps<Item extends AssetOrAssetContainer> = {
    itemComponent?: AssetItemComponent<Item> | undefined;
    item: Item;
    asset: Asset;
    selected: boolean;
    disabled: boolean;
    onOpen?: OnOpen;
} & AssetActions<Item>;

export type AssetItemCustomComponentProps<Item extends AssetOrAssetContainer> =
    PropsWithChildren<{
        item: Item;
    }>;
export type AssetItemComponent<Item extends AssetOrAssetContainer> = React.FC<
    AssetItemCustomComponentProps<Item>
>;

export type LayoutCommonProps<Item extends AssetOrAssetContainer> = {
    itemOverlay?: ItemOverlayRenderer<Item>;
};

type LayoutBaseProps<Item extends AssetOrAssetContainer> = {
    itemToAsset?: ItemToAssetFunc<Item> | undefined;
    itemComponent: AssetItemComponent<Item> | undefined;
    selection: Item[];
    disabledAssets: Item[];
    toolbarHeight: number;
} & LayoutCommonProps<Item>;

export type ItemOverlayRenderer<Item extends AssetOrAssetContainer> = (props: {
    item: Item;
}) => ReactNode;

export type LayoutProps<Item extends AssetOrAssetContainer> = {
    pages: Item[][];
    loadMore?: LoadMoreFunc;
    previewZIndex: number | undefined;
} & LayoutBaseProps<Item> &
    AssetActions<Item>;

export type LayoutPageProps<Item extends AssetOrAssetContainer> = {
    items: Item[];
    page: number;
} & LayoutBaseProps<Item> &
    AssetActions<Item>;

export type ActionsContext<Item extends AssetOrAssetContainer> = {
    extraActions?: CustomItemAction<Item>[];
    basket?: boolean;
    layout?: boolean;
    export?: boolean;
    edit?: boolean;
    move?: boolean;
    copy?: boolean;
    replace?: boolean;
    share?: boolean;
    delete?: boolean;
    restore?: boolean;
    info?: boolean;
    open?: boolean;
    saveAs?: boolean;
};

export type CustomItemAction<Item extends AssetOrAssetContainer> = {
    name: string;
    icon?: ReactNode;
    color?: ButtonProps['color'];
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

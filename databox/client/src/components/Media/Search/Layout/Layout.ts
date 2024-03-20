import {Asset} from '../../../../types';
import {MouseEvent} from 'react';

export type OnSelectAsset = (id: string, e?: MouseEvent) => void;
export type OnAddToBasket = (id: string, e?: MouseEvent) => void;
export type OnOpen = (assetId: string, renditionId: string) => void;
export type OnUnselectAsset = (id: string, e?: MouseEvent) => void;
export type OnPreviewToggle = (
    asset: Asset,
    display: boolean,
    anchorEl: HTMLElement
) => void;
export type SelectedAssets = string[];

export type TOnContextMenuOpen = (
    e: MouseEvent<HTMLElement>,
    asset: Asset,
    anchorEl?: HTMLElement
) => void;

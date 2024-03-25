import React from 'react';
import {Asset, AssetOrAssetContainer} from "../../../../types.ts";
import GroupRow from "../GroupRow";
import AssetItem from "./AssetItem";
import {LayoutPageProps, OnPreviewToggle} from "../../types.ts";
import SectionDivider from "../../SectionDivider.tsx";

type Props<Item extends AssetOrAssetContainer> = {
    onPreviewToggle?: OnPreviewToggle;
    displayAttributes: boolean;
} & LayoutPageProps<Item>;

function ListPage<Item extends AssetOrAssetContainer>({
    items,
    itemToAsset,
    onContextMenuOpen,
    onOpen,
    onPreviewToggle,
    onToggle,
    onAddToBasket,
    selection,
    toolbarHeight,
    displayAttributes,
    page,
}: Props<Item>) {
    return <>
        {page > 1 && (
            <SectionDivider
                top={toolbarHeight}
                textStyle={() => ({
                    fontWeight: 700,
                    fontSize: 15,
                })}
            >
                # {page}
            </SectionDivider>
        )}
        {items.map(item => {
            const asset: Asset = itemToAsset ? itemToAsset(item) : (item as unknown as Asset);

            return (
                <GroupRow
                    key={item.id}
                    asset={asset}
                    toolbarHeight={toolbarHeight}
                >
                    <div
                        onDoubleClick={
                            onOpen && asset.original
                                ? () => onOpen(asset, asset.original!.id)
                                : undefined
                        }
                        onContextMenu={
                            onContextMenuOpen
                                ? e => onContextMenuOpen!(e, item)
                                : undefined
                        }
                    >
                        <AssetItem
                            asset={asset}
                            item={item}
                            onToggle={onToggle}
                            selected={selection.includes(item)}
                            onAddToBasket={onAddToBasket}
                            onContextMenuOpen={onContextMenuOpen}
                            displayAttributes={displayAttributes}
                            onPreviewToggle={onPreviewToggle}

                        />
                    </div>
                </GroupRow>
            );
        })}</>
}

export default React.memo(ListPage) as typeof ListPage;

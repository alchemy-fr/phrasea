import React from 'react';
import {Asset, AssetOrAssetContainer} from "../../../../types.ts";
import GroupRow from "../GroupRow.tsx";
import {Grid} from "@mui/material";
import AssetItem from "./AssetItem.tsx";
import {LayoutPageProps, OnPreviewToggle} from "../../types.ts";
import SectionDivider from "../../SectionDivider.tsx";

type Props<Item extends AssetOrAssetContainer> = {
    onPreviewToggle?: OnPreviewToggle;
} & LayoutPageProps<Item>;

function GridPage<Item extends AssetOrAssetContainer>({
    items,
    itemToAsset,
    onContextMenuOpen,
    onOpen,
    onPreviewToggle,
    onToggle,
    selection,
    onAddToBasket,
    toolbarHeight,
    page,
    itemComponent,
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
                    <Grid
                        item
                        onDoubleClick={
                            onOpen && asset.original
                                ? () => onOpen(asset, asset.original!.id)
                                : undefined
                        }
                        onContextMenu={
                            onContextMenuOpen
                                ? e => {
                                    onContextMenuOpen!(e, item);
                                }
                                : undefined
                        }
                    >
                        <AssetItem
                            item={item}
                            itemComponent={itemComponent}
                            asset={asset}
                            onAddToBasket={onAddToBasket}
                            selected={selection.includes(item)}
                            onContextMenuOpen={onContextMenuOpen}
                            onOpen={onOpen}
                            onToggle={onToggle}
                            onPreviewToggle={onPreviewToggle}
                        />
                    </Grid>
                </GroupRow>
            );
        })}</>
}

export default React.memo(GridPage) as typeof GridPage;

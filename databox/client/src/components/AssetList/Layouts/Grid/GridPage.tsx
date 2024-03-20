import React from 'react';
import {Asset, AssetOrAssetContainer} from "../../../../types.ts";
import GroupRow from "../GroupRow.tsx";
import {Grid} from "@mui/material";
import AssetItem from "./AssetItem.tsx";
import {LayoutPageProps, OnPreviewToggle} from "../../types.ts";
import SectionDivider from "../../../Media/Search/Layout/SectionDivider.tsx";

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
    searchMenuHeight,
    page,
}: Props<Item>) {
    return <>
        {page > 1 && (
            <SectionDivider
                top={searchMenuHeight}
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
                <React.Fragment
                    key={item.id}
                >
                    <GroupRow
                        asset={asset}
                        searchMenuHeight={searchMenuHeight}
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
                </React.Fragment>
            );
        })}</>
}

export default React.memo(GridPage) as typeof GridPage;

import React from 'react';
import {Asset, AssetOrAssetContainer} from "../../../../types.ts";
import GroupRow from "../GroupRow.tsx";
import {Grid} from "@mui/material";
import AssetItem from "./AssetItem.tsx";
import {LayoutPageProps} from "../../types.ts";
import SectionDivider from "../../../Media/Search/Layout/SectionDivider.tsx";

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
}: LayoutPageProps<Item>) {
    return <>
        {items.map(item => {
            const asset: Asset = itemToAsset ? itemToAsset(item) : (item as unknown as Asset);
            const contextMenu = onContextMenuOpen;

            return (
                <>
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
                    <GroupRow
                        key={item.id}
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
                                        if (!contextMenu) {
                                            e.preventDefault();
                                            return;
                                        }
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
                                onContextMenuOpen={
                                    contextMenu ? onContextMenuOpen : undefined
                                }
                                onOpen={onOpen}
                                onToggle={onToggle}
                                onPreviewToggle={onPreviewToggle}
                            />
                        </Grid>
                    </GroupRow>
                </>
            );
        })}</>
}

export default React.memo(GridPage) as typeof GridPage;

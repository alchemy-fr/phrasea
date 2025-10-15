import React from 'react';
import {Asset, AssetOrAssetContainer} from '../../../../types';
import GroupRow from '../GroupRow';
import AssetItem from './AssetItem';
import {LayoutPageProps, OnPreviewToggle} from '../../types';
import SectionDivider from '../../SectionDivider';

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
    disabledAssets,
    toolbarHeight,
    itemComponent,
    displayAttributes,
    page,
}: Props<Item>) {
    return (
        <>
            {page > 1 && (
                <SectionDivider
                    top={toolbarHeight}
                    textSx={() => ({
                        fontWeight: 700,
                        fontSize: 15,
                    })}
                >
                    # {page}
                </SectionDivider>
            )}
            {items.map(item => {
                const asset: Asset = itemToAsset
                    ? itemToAsset(item)
                    : (item as unknown as Asset);

                return (
                    <GroupRow key={item.id} asset={asset} top={toolbarHeight}>
                        <div
                            onDoubleClick={
                                onOpen && asset.main
                                    ? () => onOpen(asset, asset.main!.id)
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
                                itemComponent={itemComponent}
                                item={item}
                                onToggle={onToggle}
                                selected={selection.includes(item)}
                                disabled={disabledAssets.includes(item)}
                                onAddToBasket={onAddToBasket}
                                onContextMenuOpen={onContextMenuOpen}
                                displayAttributes={displayAttributes}
                                onPreviewToggle={onPreviewToggle}
                            />
                        </div>
                    </GroupRow>
                );
            })}
        </>
    );
}

export default React.memo(ListPage) as typeof ListPage;

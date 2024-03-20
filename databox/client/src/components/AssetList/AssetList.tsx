import React, {Context} from 'react';
import {Asset, AssetOrAssetContainer} from "../../types.ts";
import AssetToolbar from "./AssetToolbar.tsx";
import LoadMoreButton from "./LoadMoreButton.tsx";
import {AssetSelectionContext, TSelectionContext} from "../../context/AssetSelectionContext.tsx";
import {Layout, layouts} from "./Layouts";
import {LayoutProps, OnToggle} from "./types.ts";
import {getAssetListFromEvent} from "./selection.ts";

type Props<Item extends AssetOrAssetContainer> = {
    pages: Item[][];
    loading?: boolean;
    itemToAsset?: (item: Item) => Asset;
    loadMore?: () => Promise<void>;
    selectionContext?: React.Context<TSelectionContext<Item>>;
    layout?: Layout;
};

export default function AssetList<Item extends AssetOrAssetContainer>({
    pages,
    loading,
    itemToAsset,
    loadMore,
    layout: defaultLayout,
    selectionContext: SelectionContext = AssetSelectionContext as unknown as Context<TSelectionContext<Item>>,
}: Props<Item>) {
    const [selection, setSelection] = React.useState<Item[]>([]);
    const [loadingMore, setLoadingMore] = React.useState(false);
    const [layout, setLayout] = React.useState<Layout>(defaultLayout ?? Layout.Grid);

    const onToggle = React.useCallback<OnToggle<Item>>(
        (item, e): void => {
            e?.preventDefault();
            setSelection(prev => {
                return getAssetListFromEvent(prev, item, pages, e);
            });
            // eslint-disable-next-line
        },
        [pages]
    );


    return <SelectionContext.Provider value={{
        selection,
        setSelection,
        itemToAsset,
    }}>
        <AssetToolbar
            loading={loading}
            layout={layout}
            setLayout={setLayout}
        />

        <div>
            {React.createElement(layouts[layout], {
                selection,
                // onOpen,
                // addToBasket,
                itemToAsset,
                // onContextMenuOpen,
                // onPreviewToggle,
                onToggle,
                pages,
            } as LayoutProps<Item>)}
        </div>

        {loadMore ? <LoadMoreButton
            loading={loadingMore}
            onClick={() => {
                setLoadingMore(true);
                loadMore!().finally(() => {
                    setLoadingMore(false);
                });
            }}
        /> : (
            ''
        )}
    </SelectionContext.Provider>
}

import React, {Context} from 'react';
import {Asset, AssetOrAssetContainer} from "../../types.ts";
import AssetToolbar from "./AssetToolbar.tsx";
import LoadMoreButton from "./LoadMoreButton.tsx";
import {AssetSelectionContext, TSelectionContext} from "../../context/AssetSelectionContext.tsx";
import {Layout, layouts} from "./Layouts";
import {LayoutProps, OnAddToBasket, OnOpen, OnToggle} from "./types.ts";
import {getItemListFromEvent} from "./selection.ts";
import {useBasketStore} from "../../store/basketStore.ts";

type Props<Item extends AssetOrAssetContainer> = {
    pages: Item[][];
    total?: number;
    loading?: boolean;
    itemToAsset?: (item: Item) => Asset;
    loadMore?: () => Promise<void>;
    selectionContext?: React.Context<TSelectionContext<Item>>;
    layout?: Layout;
    onOpen?: OnOpen;
    reload: () => void;
    onOpenDebug?: VoidFunction;
    searchBar?: boolean;
};

export default function AssetList<Item extends AssetOrAssetContainer>({
    pages,
    total,
    loading,
    onOpen,
    itemToAsset,
    loadMore,
    reload,
    searchBar,
    onOpenDebug,
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
                return getItemListFromEvent(prev, item, pages, e);
            });
            // eslint-disable-next-line
        },
        [pages]
    );

    const addToCurrent = useBasketStore(state => state.addToCurrent);

    const onAddToBasket = React.useCallback<OnAddToBasket>(
        (asset, e): void => {
            e?.preventDefault();
            addToCurrent([asset]);
        },
        [addToCurrent]
    );

    return <div
        style={{
            position: 'relative',
            height: '100%',
        }}
    >
        <div
            style={{
                width: '100%',
                height: '100%',
                overflow: 'auto',
            }}
        >
            <SelectionContext.Provider value={{
                selection,
                setSelection,
                itemToAsset,
            }}>
                <AssetToolbar
                    total={total}
                    loading={loading ?? false}
                    layout={layout}
                    setLayout={setLayout}
                    pages={pages}
                    reload={reload}
                    onOpenDebug={onOpenDebug}
                    selectionContext={SelectionContext}
                    searchBar={searchBar}
                />

                {React.createElement(layouts[layout], {
                    selection,
                    onOpen,
                    onAddToBasket,
                    itemToAsset,
                    // onContextMenuOpen,
                    onToggle,
                    pages,
                } as LayoutProps<Item>)}

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
        </div>
    </div>
}

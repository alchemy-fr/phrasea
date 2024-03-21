import React, {Context, MouseEvent, useEffect} from 'react';
import {Asset, AssetOrAssetContainer} from "../../types.ts";
import AssetToolbar from "./AssetToolbar.tsx";
import LoadMoreButton from "./LoadMoreButton.tsx";
import {AssetSelectionContext, TSelectionContext} from "../../context/AssetSelectionContext.tsx";
import {Layout, layouts} from "./Layouts";
import {CustomItemAction, LayoutProps, OnAddToBasket, OnContextMenuOpen, OnOpen, OnToggle} from "./types.ts";
import {getItemListFromEvent} from "./selection.ts";
import {useBasketStore} from "../../store/basketStore.ts";
import assetClasses from "./classes.ts";
import AssetContextMenu from "./AssetContextMenu.tsx";
import {PopoverPosition} from "@mui/material/Popover/Popover";

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
    actions?: CustomItemAction<Item>[];
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
    actions,
    layout: defaultLayout,
    selectionContext: SelectionContext = AssetSelectionContext as unknown as Context<TSelectionContext<Item>>,
}: Props<Item>) {
    const [selection, setSelection] = React.useState<Item[]>([]);
    const [loadingMore, setLoadingMore] = React.useState(false);
    const [layout, setLayout] = React.useState<Layout>(defaultLayout ?? Layout.Grid);
    const listRef = React.useRef<HTMLDivElement | null>(null);
    const [toolbarHeight, setToolbarHeight] = React.useState(0);
    const [anchorElMenu, setAnchorElMenu] = React.useState<null | {
        item: Item;
        asset: Asset;
        pos: PopoverPosition;
        anchorEl: HTMLElement | undefined;
    }>(null);

    useEffect(() => {
        if (!listRef.current) {
            return;
        }

        const resizeObserver = new ResizeObserver(entries => {
            setToolbarHeight(entries[0].target.clientHeight);
        });

        resizeObserver.observe(listRef.current!.querySelector(`.${assetClasses.assetToolbar}`)!);

        return () => {
            resizeObserver.disconnect();
        };
    }, [listRef.current]);

    React.useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.ctrlKey && e.key === 'a') {
                const activeElement = document.activeElement;
                if (
                    activeElement &&
                    ['input', 'select', 'button', 'textarea'].includes(
                        activeElement.tagName.toLowerCase()
                    )
                ) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                setSelection(pages.flat());
            }
        };
        window.addEventListener('keydown', handler);

        return () => {
            window.removeEventListener('keydown', handler);
        };
    }, [pages]);

    const onContextMenuOpen = React.useCallback<OnContextMenuOpen<Item>>(
        (e: MouseEvent<HTMLElement>, item: Item, anchorEl?: HTMLElement) => {
            e.preventDefault();
            e.stopPropagation();
            setAnchorElMenu(p => {
                if (p && p.anchorEl === anchorEl) {
                    return null;
                }

                return {
                    item,
                    asset: itemToAsset ? itemToAsset(item) : (item as unknown as Asset),
                    pos: {
                        left: e.clientX + 2,
                        top: e.clientY,
                    },
                    anchorEl,
                };
            });
        },
        [setAnchorElMenu, itemToAsset]
    );

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
        ref={listRef}
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
                    actions={actions}
                />

                {React.createElement(layouts[layout], {
                    selection,
                    onOpen,
                    onAddToBasket,
                    itemToAsset,
                    onContextMenuOpen,
                    onToggle,
                    pages,
                    toolbarHeight,
                } as LayoutProps<Item>)}

                {loadMore ? <LoadMoreButton
                    loading={loadingMore}
                    onClick={() => {
                        setLoadingMore(true);
                        loadMore().finally(() => {
                            console.log('finally');
                            setLoadingMore(false);
                        });
                    }}
                /> : (
                    ''
                )}

                {anchorElMenu ? (
                    <AssetContextMenu
                        item={anchorElMenu.item}
                        asset={anchorElMenu.asset}
                        anchorPosition={anchorElMenu.pos}
                        anchorEl={anchorElMenu.anchorEl}
                        onClose={() => setAnchorElMenu(null)}
                    />
                ) : ''}
            </SelectionContext.Provider>
        </div>
    </div>
}

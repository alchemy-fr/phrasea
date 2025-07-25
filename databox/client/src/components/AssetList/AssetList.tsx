import React, {Context, MouseEvent, useCallback, useEffect} from 'react';
import {Asset, AssetOrAssetContainer, StateSetter} from '../../types';
import AssetToolbar from './AssetToolbar';
import {
    AssetSelectionContext,
    TSelectionContext,
} from '../../context/AssetSelectionContext';
import {Layout, layouts} from './Layouts';
import {
    ActionsContext,
    AssetItemComponent,
    LayoutCommonProps,
    LayoutProps,
    LoadMoreFunc,
    OnAddToBasket,
    OnAssetContextMenuOpen,
    OnOpen,
    OnSelectionChange,
    OnToggle,
    ReloadFunc,
} from './types';
import {getItemListFromEvent} from './selection';
import createStateSetterProxy from '@alchemy/react-hooks/src/createStateSetterProxy';
import {useBasketStore} from '../../store/basketStore';
import assetClasses from './classes';
import AssetContextMenu from './AssetContextMenu';
import {SelectionActionConfigProps} from './Toolbar/SelectionActions';
import {useSelectAllKey} from '../../hooks/useSelectAllKey.ts';
import {createDefaultActionsContext} from './actionContext.ts';
import useUpdateEffect from '@alchemy/react-hooks/src/useUpdateEffect';
import {useContextMenu} from '../../hooks/useContextMenu.ts';
import {useAssetStore} from '../../store/assetStore.ts';

type Props<Item extends AssetOrAssetContainer> = {
    pages: Item[][];
    total?: number;
    loading?: boolean;
    itemToAsset?: (item: Item) => Asset;
    loadMore?: LoadMoreFunc | undefined;
    selectionContext?: React.Context<TSelectionContext<Item>>;
    layout?: Layout;
    onOpen?: OnOpen;
    reload?: ReloadFunc;
    onOpenDebug?: VoidFunction;
    searchBar?: boolean;
    actionsContext?: ActionsContext<Item>;
    itemOverlay?: LayoutCommonProps<Item>;
    subSelection?: Item[];
    onSelectionChange?: OnSelectionChange<Item>;
    defaultSelection?: Item[];
    itemComponent?: AssetItemComponent<Item>;
    previewZIndex?: number;
} & SelectionActionConfigProps &
    LayoutCommonProps<Item>;

export default function AssetList<Item extends AssetOrAssetContainer>({
    pages,
    total,
    loading,
    onOpen,
    itemToAsset: itemToAssetProxy,
    loadMore,
    reload,
    searchBar,
    defaultSelection = [],
    onOpenDebug,
    onSelectionChange,
    subSelection,
    itemComponent,
    actionsContext = createDefaultActionsContext(),
    itemOverlay,
    previewZIndex,
    layout: defaultLayout,
    selectionContext:
        SelectionContext = AssetSelectionContext as unknown as Context<
            TSelectionContext<Item>
        >,
    ...selectionActionsProps
}: Props<Item>) {
    const [selection, setSelectionPrivate] =
        React.useState<Item[]>(defaultSelection);
    const [layout, setLayout] = React.useState<Layout>(
        defaultLayout ?? Layout.Grid
    );
    const listRef = React.useRef<HTMLDivElement | null>(null);
    const [toolbarHeight, setToolbarHeight] = React.useState(0);

    const {
        contextMenu,
        onContextMenuOpen: onContextMenuOpenProxy,
        onContextMenuClose,
    } = useContextMenu<{
        asset: Asset;
        item: Item;
    }>();

    const storeAssets = useAssetStore(s => s.assets);
    const itemToAsset = useCallback(
        (item: Item) => {
            const asset: Asset = itemToAssetProxy
                ? itemToAssetProxy(item)
                : (item as unknown as Asset);

            return storeAssets[asset.id] ?? asset;
        },
        [itemToAssetProxy, storeAssets]
    );

    const onContextMenuOpen = React.useCallback<OnAssetContextMenuOpen<Item>>(
        (e: MouseEvent<HTMLElement>, item: Item, anchorEl?: HTMLElement) => {
            onContextMenuOpenProxy(
                e,
                {
                    item,
                    asset: itemToAsset
                        ? itemToAsset(item)
                        : (item as unknown as Asset),
                },
                anchorEl
            );
        },
        [onContextMenuOpenProxy, itemToAsset]
    );

    React.useEffect(() => {
        if (subSelection) {
            setSelectionPrivate(subSelection);
        }
    }, [subSelection]);

    const setSelection = React.useMemo<StateSetter<Item[]>>(() => {
        if (!onSelectionChange) {
            return setSelectionPrivate;
        }

        return handler => {
            setSelectionPrivate(
                createStateSetterProxy(handler, n => {
                    onSelectionChange(n);

                    return n;
                })
            );
        };
    }, [onSelectionChange, setSelectionPrivate]);

    useUpdateEffect(() => {
        setSelectionPrivate([]);
    }, [pages[0]]);

    useEffect(() => {
        if (!listRef.current) {
            return;
        }

        const resizeObserver = new ResizeObserver(entries => {
            setToolbarHeight(entries[0].target.clientHeight);
        });

        resizeObserver.observe(
            listRef.current!.querySelector(`.${assetClasses.assetToolbar}`)!
        );

        return () => {
            resizeObserver.disconnect();
        };
    }, [listRef.current]);

    useSelectAllKey(() => {
        setSelection(pages.flat());
    }, [pages]);

    const onToggle = React.useCallback<OnToggle<Item>>(
        (item, e): void => {
            e?.preventDefault();
            setSelection(prev => {
                return getItemListFromEvent(prev, item, pages, e);
            });
        },
        [pages]
    );

    const addToCurrent = useBasketStore(state => state.addToCurrent);

    const onAddToBasket = React.useMemo<OnAddToBasket | undefined>(() => {
        if (actionsContext.basket) {
            return (asset, e): void => {
                e?.preventDefault();
                addToCurrent([asset]);
            };
        }
    }, [addToCurrent, actionsContext.basket]);

    return (
        <div
            ref={listRef}
            style={{
                position: 'relative',
                height: '100%',
            }}
        >
            <div
                className={assetClasses.scrollable}
                style={{
                    width: '100%',
                    height: '100%',
                    overflow: 'auto',
                }}
            >
                <SelectionContext.Provider
                    value={{
                        selection,
                        setSelection,
                        itemToAsset,
                    }}
                >
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
                        actionsContext={actionsContext}
                        {...selectionActionsProps}
                    />

                    {React.createElement(layouts[layout], {
                        selection,
                        onOpen,
                        onAddToBasket,
                        itemToAsset,
                        onContextMenuOpen,
                        onToggle,
                        pages,
                        loadMore,
                        toolbarHeight,
                        itemComponent,
                        previewZIndex,
                        itemOverlay,
                    } as LayoutProps<Item>)}

                    {contextMenu ? (
                        <AssetContextMenu
                            contextMenu={contextMenu}
                            actionsContext={actionsContext}
                            onClose={onContextMenuClose}
                            reload={reload}
                            setSelection={setSelection}
                        />
                    ) : (
                        ''
                    )}
                </SelectionContext.Provider>
            </div>
        </div>
    );
}

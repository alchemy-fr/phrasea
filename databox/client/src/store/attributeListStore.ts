import {create} from 'zustand';
import {AttributeDefinition, AttributeList, AttributeListItem, AttributeListItemType} from '../types';
import {
    addToAttributeList,
    deleteAttributeList,
    getAttributeList,
    GetAttributeListOptions,
    getAttributeLists,
    removeFromAttributeList, sortAttributeList,
} from '../api/attributeList';

type State = {
    lists: AttributeList[];
    current: AttributeList | undefined;
    nextUrl?: string | undefined;
    loaded: boolean;
    loading: boolean;
    loadingCurrent: boolean;
    loadingMore: boolean;
    total?: number;
    hasMore: () => boolean;
    load: (params?: GetAttributeListOptions, force?: boolean) => Promise<void>;
    loadMore: () => Promise<void>;
    addAttributeList: (list: AttributeList) => void;
    loadList: (id: string) => Promise<AttributeList>;
    updateAttributeList: (data: AttributeList) => void;
    deleteAttributeList: (id: string) => void;
    addToCurrent: (items: AttributeListItem[]) => void;
    addToList: (listId: string | undefined, items: AttributeListItem[]) => void;
    sortList: (listId: string, items: string[]) => void;
    toggleDefinition: (definition: AttributeDefinition) => void;
    removeFromList: (listId: string, ids: string[]) => void;
    setCurrent: (data: AttributeList | undefined) => Promise<void>;
    shouldSelectAttributeList: () => boolean;
};

export const useAttributeListStore = create<State>((set, getState) => ({
    loadingMore: false,
    loaded: false,
    loading: false,
    loadingCurrent: false,
    current: undefined,
    lists: [],

    load: async (params, force) => {
        if (getState().loaded && !force) {
            return;
        }

        set({
            loading: true,
        });

        try {
            const data = await getAttributeLists(undefined, params);

            set(state => ({
                lists: data.result,
                total: data.total,
                loading: false,
                loaded: true,
                current: data.total === 1 ? data.result[0] : state.current,
                nextUrl: data.next || undefined,
            }));
        } catch (e: any) {
            set({loading: false});
            throw e;
        }
    },

    hasMore() {
        return !!getState().nextUrl;
    },

    setCurrent: async data => {
        if (!data) {
            set({
                current: undefined,
                loadingCurrent: false,
            });

            return;
        }

        set({
            current: data,
            loadingCurrent: true,
        });

        try {
            const list = await getAttributeList(data.id);
            set({
                current: list,
                loadingCurrent: false,
            });
        } catch (e: any) {
            set({
                loadingCurrent: false,
            });
        }
    },

    shouldSelectAttributeList: () => {
        const {current, loading, lists} = getState();

        if (current) {
            return false;
        }

        if (loading) {
            return true;
        }

        return lists.length > 1;
    },

    updateAttributeList: data => {
        set(state => ({
            lists: state.lists.map(b => {
                if (b.id === data.id) {
                    return {
                        ...b,
                        ...data,
                    };
                }

                return b;
            }),
            current: state.current?.id === data.id ? data : state.current,
        }));
    },

    loadMore: async () => {
        const nextUrl = getState().nextUrl;
        if (!nextUrl) {
            return;
        }

        set({loadingMore: true});
        try {
            const data = await getAttributeLists(nextUrl);

            set(state => ({
                lists: state.lists.concat(data.result),
                total: data.total,
                loadingMore: false,
                nextUrl: data.next || undefined,
            }));
        } catch (e: any) {
            set({loadingMore: false});

            throw e;
        }
    },

    addAttributeList(list) {
        set(state => ({
            lists: [list].concat(state.lists),
        }));
    },

    deleteAttributeList: async id => {
        await deleteAttributeList(id);

        set(state => ({
            lists: state.lists.filter(b => b.id !== id),
            current: state.current?.id === id ? undefined : state.current,
        }));
    },

    toggleDefinition: definition => {
        const state = getState();
        const current = state.current;
        const defId = definition.id;

        if (current) {
            const item = current.items!.find(i => i.definition === defId || i.key === defId);
            if (item?.id) {
                state.removeFromList(current.id, [item.id]);

                return;
            }
        }

        state.addToCurrent([attributeDefinitionToItem(definition)]);
    },

    loadList: async (id: string) => {
        const list = await getAttributeList(id!);
        set(state => {
            return ({
                current: state.current?.id === list.id ? list : state.current,
                lists: replaceList(state.lists, list),
            });
        });

        return list;
    },

    addToList: async (listId, items) => {
        try {
            const list = await addToAttributeList(listId, {
                // @ts-expect-error id cannot be undefined
                items: items.map(i => ({
                    ...i,
                    id: i.id?.startsWith(tmpIdPrefix) ? undefined : i.id,
                })),
            });
            set(state => ({
                current: list,
                lists: replaceList(state.lists, list),
            }));
        } catch (e: any) {
            if (listId) {
                set(state => {
                    if (state.current?.id === listId) {
                        const curr = state.current!;

                        return {
                            current: {
                                ...curr,
                                items: curr.items,
                            },
                        };
                    }

                    return state;
                });
            }
        }
    },

    addToCurrent: async items => {
        const state = getState();
        state.addToList(state.current?.id, items);
    },

    sortList: async (listId, items) => {
        set(p => ({
            lists: p.lists.map(b => {
                if (b.id === listId && b.items) {
                    return getReorderedListItems(b, items);
                }

                return b;
            }),
            current: p.current?.id === listId ? getReorderedListItems(p.current, items) : p.current,
        }));

        await sortAttributeList(listId, items);
    },

    removeFromList: async (listId, items) => {
        let current: AttributeList | undefined = getState().current;
        if (current && current.id !== listId) {
            current = undefined;
        }

        if (current && current.items !== undefined) {
            set({
                current: {
                    ...current,
                    items: current.items.filter(
                        d => !items.some(i => i === d.id)
                    ),
                },
            });
        }

        const itemsToRemove = items.filter(i => !i.startsWith(tmpIdPrefix));
        if (itemsToRemove.length > 0) {
            const list = await removeFromAttributeList(listId, itemsToRemove);
            set(state => ({
                lists: replaceList(state.lists, list),
            }));
        }
    },
}));

function replaceList(prev: AttributeList[], list: AttributeList): AttributeList[] {
    return prev.some(l => l.id === list.id) ? prev.map(l => l.id === list.id ? list : l)
        : prev.concat([list])
}

export function attributeDefinitionToItem(
    definition: AttributeDefinition
): AttributeListItem {
    const isBI = definition.builtIn;

    return {
        id: tmpIdPrefix +definition.id,
        type: isBI
            ? AttributeListItemType.BuiltIn
            : AttributeListItemType.Definition,
        definition: isBI ? undefined : definition.id,
        key: isBI ? definition.id : undefined,
    };
}

let inc = 1;

export function createDivider(
    title: string,
): AttributeListItem {
    return {
        id: tmpIdPrefix + (inc++).toString(),
        type: AttributeListItemType.Divider,
        key: title,
    };
}

const tmpIdPrefix = '_tmp_';

export function hasDefinitionInItems(items: AttributeListItem[], id: string): boolean {
    return items.some(i => i.definition === id || i.key === id);
}

function getReorderedListItems(list: AttributeList, order: string[]): AttributeList {
    if (!list.items) {
        return list;
    }

    return {
        ...list,
        items: order.map(id => list.items!.find(i => i.id === id)).filter(i => !!i),
    }
}

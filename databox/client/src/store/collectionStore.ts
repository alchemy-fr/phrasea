import {create} from 'zustand'
import {Collection, Workspace} from "../types.ts";
import {collectionChildrenLimit, CollectionOptions, collectionSecondLimit, getCollections} from "../api/collection.ts";

export type CollectionPager = {
    items: Collection[];
    loadingMore: boolean;
    expanding: boolean;
    total?: number;
}

type State = {
    collections: Record<string, Collection>;
    tree: Record<string, CollectionPager>;
    setRootCollections: (workspaces: Workspace[]) => void;
    updateCollection: (collection: Collection) => void;
    loadChildren: (parentId: string) => Promise<void>;
    loadMore: (pagerId: string) => Promise<void>;
    addCollection: (collection: Collection, pagerId: string, parentId?: string) => void;
    deleteCollection: (id: string, pagerId: string, parentId?: string) => void;
}

function updateCollectionByReference(state: Record<string, Collection>, collection: Collection): Collection {
    if (state[collection.id]) {
        (Object.keys(collection) as (keyof typeof collection)[]).forEach((k) => {
            // @ts-expect-error key typing
            state[collection.id][k] = collection[k];
        });
    } else {
        state[collection.id] = {...collection};
    }

    return state[collection.id];
}


export const useCollectionStore = create<State>((set, getState) => ({
    collections: {},
    tree: {},

    loadChildren: async (parentId: string) => {
        const timeout = setTimeout(() => {
            set((state) => {
                const tree = {...state.tree};

                tree[parentId] = {
                    ...(tree[parentId] ?? {}),
                    expanding: true,
                };

                return {tree};
            })
        }, 800);

        const data = await getCollections({
            parent: parentId,
            limit: collectionSecondLimit,
            childrenLimit: collectionChildrenLimit,
        });
        clearTimeout(timeout);

        set((state) => {
            const tree = {...state.tree};
            const newCollections = {...state.collections};

            const items = data.result.map(c => {
                return updateCollectionByReference(newCollections, c);
            });

            tree[parentId] = {
                ...(tree[parentId] ?? {}),
                items,
                total: data.total,
                expanding: false,
                loadingMore: false,
            };

            return {
                tree,
                collections: newCollections,
            };
        })
    },

    loadMore: async (pagerId: string, parentId?: string) => {
        const pager = getState().tree[pagerId]!;
        const nextPage = getNextPage(pager) ?? 1;

        const setLoading = (loading: boolean) => {
            set((state) => {
                const tree = {...state.tree};

                tree[pagerId] = {
                    ...(tree[pagerId] ?? {}),
                    loadingMore: loading,
                };

                return {tree};
            });
        }


        const options: CollectionOptions = {
            page: nextPage,
            limit: collectionSecondLimit,
            childrenLimit: collectionChildrenLimit,
        };

        if (parentId) {
            options.parent = parentId;
        } else {
            options.workspaces = [pagerId];
        }

        setLoading(true);
        try {
            const data = await getCollections(options);

            set((state) => {
                const tree = {...state.tree};
                const newCollections = {...state.collections};

                const items = data.result.map(c => {
                    return updateCollectionByReference(newCollections, c);
                });

                const existingTree = tree[pagerId]!;

                tree[pagerId] = {
                    ...existingTree,
                    items: existingTree.items.concat(items),
                    total: data.total,
                    expanding: false,
                    loadingMore: false,
                };

                return {
                    tree,
                    collections: newCollections,
                };
            })
        } catch (e) {
            setLoading(false);
        }
    },

    setRootCollections: (workspaces: Workspace[]) => {
        set((state) => {
            const newCollections = {...state.collections};
            const tree = {...state.tree};

            workspaces.forEach(ws => {
                const wsId = ws.id;

                const items = ws.collections.map(c => {
                    return updateCollectionByReference(newCollections, c);
                });

                tree[wsId] = {
                    items,
                    expanding: false,
                    loadingMore: false,
                };
            });

            return {
                collections: newCollections,
                tree,
            };
        })
    },

    updateCollection: (collection: Collection) => {
        set((state) => {
            const newCollections = {...state.collections};

            updateCollectionByReference(newCollections, collection);

            return {
                collections: newCollections,
            };
        })
    },

    addCollection: (collection: Collection, pagerId: string, parentId?: string) => {
        set((state) => {
            const newCollections = {...state.collections};
            const tree = {...state.tree};

            const c = updateCollectionByReference(newCollections, collection);

            if (parentId) {
                const parentCollection = newCollections[parentId];
                if (parentCollection && (parentCollection.children?.length ?? 0) === 0) {
                    updateCollectionByReference(newCollections, {
                        ...parentCollection,
                        children: [c]
                    });
                }
            }

            const pager = tree[pagerId] ?? {
                items: [],
                total: 0,
            };

            tree[pagerId] = {
                ...pager,
                items: pager.items.concat([c]),
                total: (pager.total ?? 0) + 1,
            };

            return {
                tree,
                collections: newCollections,
            };
        })
    },

    deleteCollection: (id: string, pagerId: string, parentId?: string) => {
        set((state) => {
            const newCollections = {...state.collections};
            const collection = newCollections[id];
            if (!collection) {
                return {};
            }

            const tree = {...state.tree};

            delete newCollections[id];

            if (parentId) {
                const parentCollection = newCollections[parentId];
                if (parentCollection) {
                    updateCollectionByReference(newCollections, {
                        ...parentCollection,
                        children: parentCollection.children?.filter(child => child.id !== id)
                    });
                }
            }

            const pager = tree[pagerId] ?? {
                items: [],
                total: 0,
            };

            tree[pagerId] = {
                ...pager,
                items: pager.items.filter(child => child.id !== id),
                total: (pager.total ?? 1) - 1,
            };

            return {
                tree,
                collections: newCollections,
            };
        })
    }
}))

export function getNextPage(pager: CollectionPager): number | undefined {
    if (pager.items.length >= collectionChildrenLimit) {
        if (pager.total) {
            if (pager.items.length < pager.total) {
                return (
                    Math.floor(
                        pager.items.length / collectionSecondLimit
                    ) + 1
                );
            }
        } else {
            return 1;
        }
    }
}

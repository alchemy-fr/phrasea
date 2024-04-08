import {create} from 'zustand';
import {Collection, Workspace} from '../types';
import {
    collectionChildrenLimit,
    CollectionOptions,
    collectionSecondLimit,
    getCollections,
} from '../api/collection';

export type CollectionPager = {
    items: CollectionExtended[];
    loadingMore: boolean;
    expanding: boolean;
    total?: number;
};

type CollectionExtended = {
    workspaceId: string;
    parentId: string | undefined;
} & Collection;

type State = {
    collections: Record<string, CollectionExtended>;
    tree: Record<string, CollectionPager>;
    setRootCollections: (workspaces: Workspace[]) => void;
    updateCollection: (collection: Collection) => void;
    partialUpdateCollection: (id: string, updates: Partial<Collection>) => void;
    loadChildren: (workspaceId: string, parentId: string) => Promise<void>;
    loadRoot: (workspaceId: string) => Promise<void>;
    loadMore: (workspaceId: string, parentId?: string) => Promise<void>;
    addCollection: (
        collection: Collection,
        workspaceId: string,
        parentId?: string
    ) => void;
    deleteCollection: (id: string) => void;
    moveCollection: (id: string, to: string | undefined) => void;
};

function updateCollectionByReference(
    state: Record<string, Collection>,
    collection: Collection | CollectionExtended,
    workspaceId: string,
    parentId?: string | undefined
): CollectionExtended {
    const c = state[collection.id];

    if (c) {
        (Object.keys(collection) as (keyof typeof collection)[]).forEach(k => {
            // @ts-expect-error key typing
            c[k] = collection[k];
        });
    } else {
        return (state[collection.id] = {
            ...collection,
            workspaceId,
            parentId,
        } as CollectionExtended);
    }

    return c as CollectionExtended;
}

export const useCollectionStore = create<State>((set, getState) => ({
    collections: {},
    tree: {},

    loadChildren: async (workspaceId, parentId) => {
        const timeout = setTimeout(() => {
            set(createPagerExpandingSetter(parentId));
        }, 800);

        const data = await getCollections({
            parent: parentId,
            limit: collectionSecondLimit,
            childrenLimit: collectionChildrenLimit,
        });
        clearTimeout(timeout);

        set(state => {
            const tree = {...state.tree};
            const newCollections = {...state.collections};

            const items = data.result.map(c => {
                return updateCollectionByReference(
                    newCollections,
                    c,
                    workspaceId,
                    parentId
                );
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
        });
    },

    loadRoot: async workspaceId => {
        const timeout = setTimeout(() => {
            set(createPagerExpandingSetter(workspaceId));
        }, 800);

        const data = await getCollections({
            workspaces: [workspaceId],
            limit: collectionSecondLimit,
            childrenLimit: collectionChildrenLimit,
        });
        clearTimeout(timeout);

        set(state => {
            const tree = {...state.tree};
            const newCollections = {...state.collections};

            const items = data.result.map(c => {
                return updateCollectionByReference(
                    newCollections,
                    c,
                    workspaceId
                );
            });

            tree[workspaceId] = {
                ...(tree[workspaceId] ?? {}),
                items,
                total: data.total,
                expanding: false,
                loadingMore: false,
            };

            return {
                tree,
                collections: newCollections,
            };
        });
    },

    loadMore: async (workspaceId, parentId) => {
        const pagerId = parentId ?? workspaceId;
        const pager = getState().tree[pagerId];
        if (!pager) {
            return;
        }

        const nextPage = getNextPage(pager) ?? 1;

        const setLoading = (loading: boolean) => {
            set(state => {
                const tree = {...state.tree};
                if (!tree[pagerId]) {
                    return {};
                }

                tree[pagerId] = {
                    ...tree[pagerId],
                    loadingMore: loading,
                };

                return {tree};
            });
        };

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

            set(state => {
                const tree = {...state.tree};
                const newCollections = {...state.collections};

                const items = data.result.map(c => {
                    return updateCollectionByReference(
                        newCollections,
                        c,
                        workspaceId,
                        parentId
                    );
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
            });
        } catch (e) {
            setLoading(false);
        }
    },

    setRootCollections: workspaces => {
        set(state => {
            const newCollections = {...state.collections};
            const tree = {...state.tree};

            workspaces.forEach(ws => {
                const wsId = ws.id;

                const items = ws.collections.map(c => {
                    return updateCollectionByReference(
                        newCollections,
                        c,
                        ws.id
                    );
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
        });
    },

    updateCollection: collection => {
        getState().partialUpdateCollection(collection.id, collection);
    },

    partialUpdateCollection: (id, updates) => {
        set(state => {
            const newCollections = {...state.collections};

            const oldColl: CollectionExtended | undefined = newCollections[id];
            if (!oldColl) {
                return {};
            }

            const oldPublic = oldColl.public;
            const oldShared = oldColl.shared;

            updateCollectionByReference(
                newCollections,
                {
                    ...oldColl,
                    ...updates,
                },
                oldColl.workspaceId,
                oldColl.parentId
            );

            const applyToChildren = (
                parentId: string,
                specs: Record<string, any>
            ) => {
                if (state.tree[parentId]) {
                    state.tree[parentId].items.forEach(c => {
                        if (newCollections[c.id]) {
                            Object.keys(specs).forEach(k => {
                                // @ts-expect-error keys...
                                newCollections[c.id][k] = specs[k];
                            });

                            applyToChildren(c.id, specs);
                        }
                    });
                }
            };

            const subSpecs: {
                public?: boolean;
                shared?: boolean;
            } = {};

            if (!oldPublic && updates.public) {
                subSpecs.public = true;
            }
            if (!oldShared && updates.shared) {
                subSpecs.shared = true;
            }

            if (Object.keys(subSpecs).length > 0) {
                applyToChildren(id, subSpecs);
            }

            const shouldRefresh =
                (oldPublic && false === updates.public) ||
                (oldShared && false === updates.shared);
            if (shouldRefresh && state.tree[id]) {
                state.loadChildren(oldColl.workspaceId, id);
            }

            return {
                collections: newCollections,
            };
        });
    },

    addCollection: (collection, workspaceId, parentId) => {
        set(state => {
            const newCollections = {...state.collections};
            const tree = {...state.tree};

            const c = updateCollectionByReference(
                newCollections,
                collection,
                workspaceId,
                parentId
            );

            if (parentId) {
                const parentCollection = newCollections[parentId];
                if (
                    parentCollection &&
                    (parentCollection.children?.length ?? 0) === 0
                ) {
                    newCollections[parentCollection.id].children = [c];
                }
            }

            const pagerId = c.parentId ?? c.workspaceId;
            const pager = tree[pagerId] ?? {
                items: [],
                loadingMore: false,
                expanding: false,
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
        });
    },

    deleteCollection: id => {
        set(state => {
            const newCollections = {...state.collections};
            const collection = newCollections[id];
            if (!collection) {
                return {};
            }
            const {parentId, workspaceId} = newCollections[id];
            const pagerId = parentId ?? workspaceId;

            delete newCollections[id];

            if (parentId) {
                const parentCollection = newCollections[parentId];
                if (parentCollection) {
                    newCollections[parentCollection.id].children =
                        parentCollection.children?.filter(
                            child => child.id !== id
                        );
                }
            }

            const tree = {...state.tree};
            const pager = tree[pagerId];
            if (!pager) {
                return {};
            }

            tree[pagerId] = {
                ...pager,
                items: pager.items.filter(child => child.id !== id),
                total: (pager.total ?? 1) - 1,
            };

            return {
                tree,
                collections: newCollections,
            };
        });
    },

    moveCollection: (id, to) => {
        set(state => {
            const newCollections = {...state.collections};
            const collection = newCollections[id];
            if (!collection) {
                return {};
            }

            const {workspaceId, parentId} = collection;
            const oldPagerId = parentId ?? workspaceId;

            collection.parentId = to;

            const tree = {...state.tree};

            if (parentId) {
                const oldParent = newCollections[parentId];
                if (oldParent) {
                    newCollections[oldParent.id].children =
                        oldParent.children?.filter(child => child.id !== id);
                }
            }

            if (to) {
                const newParent = newCollections[to];
                if (newParent && (newParent.children?.length ?? 0) === 0) {
                    newCollections[newParent.id].children = [collection];
                }
            }

            const oldPager = tree[oldPagerId];
            if (oldPager) {
                tree[oldPagerId] = {
                    ...oldPager,
                    items: oldPager.items.filter(
                        child => child.id !== collection.id
                    ),
                    total: (oldPager.total ?? 1) - 1,
                };
            }

            const newPagerId = to ?? workspaceId;
            const newPager = tree[newPagerId];
            if (newPager) {
                tree[newPagerId] = {
                    ...newPager,
                    items: newPager.items.concat([collection]),
                    total: (newPager.total ?? 0) + 1,
                };
            }

            return {
                tree,
                collections: newCollections,
            };
        });
    },
}));

export function getNextPage(pager: CollectionPager): number | undefined {
    if (pager.items.length >= collectionChildrenLimit) {
        if (pager.total) {
            if (pager.items.length < pager.total) {
                return (
                    Math.floor(pager.items.length / collectionSecondLimit) + 1
                );
            }
        } else {
            return 1;
        }
    }
}

const createPagerExpandingSetter = (pagerId: string) => {
    return (state: State) => {
        const tree = {...state.tree};

        tree[pagerId] = {
            ...(tree[pagerId] ?? {
                loadingMore: false,
                items: [],
            }),
            ...tree[pagerId],
            expanding: true,
        };

        return {tree};
    };
};

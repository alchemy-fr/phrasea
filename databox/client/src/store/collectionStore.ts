import {create, StoreApi} from 'zustand';
import {Collection} from '../types';
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

type JustCreatedIndex = Record<string, Record<string, CollectionExtended>>;

type State = {
    collections: Record<string, CollectionExtended>;
    justCreated: JustCreatedIndex;
    tree: Record<string, CollectionPager>;
    updateCollection: (collection: Collection) => void;
    partialUpdateCollection: (id: string, updates: Partial<Collection>) => void;
    load: (
        workspaceId: string,
        parentId?: string,
        force?: boolean
    ) => Promise<void>;
    loadMore: (workspaceId: string, parentId?: string) => Promise<void>;
    addCollection: (
        collection: Collection,
        workspaceId: string,
        parentId?: string
    ) => void;
    deleteCollection: (id: string) => void;
    moveCollection: (id: string, to: string | undefined) => void;
};

export const useCollectionStore = create<State>((set, getState) => ({
    collections: {},
    tree: {},
    justCreated: {},

    load: async (workspaceId, parentId, force) => {
        const pagerId = parentId ?? workspaceId;
        if (!force && getState().tree[pagerId]) {
            return;
        }

        const timeout = setTimeout(() => {
            set(createPagerExpandingSetter(pagerId, parentId));
        }, 800);

        const data = await getCollections({
            workspaces: [workspaceId],
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

            const jc = state.justCreated[pagerId] ?? false;
            if (jc) {
                Object.keys(jc as Record<string, CollectionExtended>).forEach(
                    cId => {
                        if (!items.some(c => c.id === cId)) {
                            items.push(jc[cId]);
                            ++data.total;
                        }
                    }
                );
            }

            tree[pagerId] = {
                ...(tree[pagerId] ?? {}),
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
                state.load(oldColl.workspaceId, id);
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
            let justCreated = state.justCreated;

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

                    justCreated = addJustCreated(justCreated, parentId, c, set);
                }
            } else {
                justCreated = addJustCreated(justCreated, workspaceId, c, set);
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
                justCreated,
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

const createPagerExpandingSetter = (
    pagerId: string,
    parentId: string | undefined
) => {
    return (state: State) => {
        const tree = {...state.tree};

        const parentCollection = parentId
            ? state.collections[parentId]
            : undefined;

        tree[pagerId] = {
            ...(tree[pagerId] ?? {
                loadingMore: false,
                items: parentCollection ? parentCollection.children : [],
            }),
            expanding: true,
        };

        return {tree};
    };
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

function addJustCreated(
    store: JustCreatedIndex,
    parentId: string,
    collection: CollectionExtended,
    set: StoreApi<State>['setState']
): JustCreatedIndex {
    const newStore = {...store};
    newStore[parentId] = {
        ...(newStore[parentId] ?? {}),
    };
    newStore[parentId][collection.id] = collection;

    setTimeout(() => {
        set((prev: State) => {
            const s = {...prev.justCreated};

            if (s[parentId]) {
                s[parentId] = {
                    ...(s[parentId] ?? {}),
                };
                delete s[parentId][collection.id];
            }

            return s;
        });
    }, 10000);

    return store;
}

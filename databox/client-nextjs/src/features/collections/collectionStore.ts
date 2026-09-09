import {create} from 'zustand';
import type {Collection, Workspace} from '@/types/api';
import {
    collectionChildrenLimit,
    collectionPageLimit,
    getCollectionAscendants,
    getCollections,
    getWorkspaces,
} from '@/lib/api/collections';

export type CollectionNode = Collection & {
    workspaceId: string;
    parentId?: string;
};

export type Pager = {
    ids: string[];
    total?: number;
    next?: string;
    loading: boolean;
    loaded: boolean;
};

type State = {
    workspaces: Workspace[];
    workspacesLoaded: boolean;
    workspacesLoading: boolean;
    collections: Record<string, CollectionNode>;
    /** Children pagers keyed by parent collection id, or by workspace id for roots */
    pagers: Record<string, Pager>;
    loadWorkspaces: (force?: boolean) => Promise<void>;
    upsertWorkspace: (workspace: Workspace) => void;
    loadChildren: (
        workspaceId: string,
        parentId?: string,
        force?: boolean
    ) => Promise<void>;
    loadMore: (workspaceId: string, parentId?: string) => Promise<void>;
    loadAscendants: (collectionId: string) => Promise<Collection>;
    upsertCollection: (collection: Collection, workspaceId?: string) => void;
    patchCollections: (ids: string[], patch: Partial<Collection>) => void;
    removeCollection: (id: string) => void;
    moveCollection: (id: string, newParentId: string | undefined) => void;
};

function pagerKey(workspaceId: string, parentId?: string): string {
    return parentId ?? `ws:${workspaceId}`;
}

function ingest(
    collections: Record<string, CollectionNode>,
    pagers: Record<string, Pager>,
    c: Collection,
    workspaceId: string,
    parentId: string | undefined
): void {
    collections[c.id] = {...collections[c.id], ...c, workspaceId, parentId};
    if (c.children) {
        const key = pagerKey(workspaceId, c.id);
        const existing = pagers[key];
        if (!existing?.loaded) {
            pagers[key] = {
                ids: c.children.map(child => child.id),
                loading: false,
                loaded: false,
                total: undefined,
            };
            c.children.forEach(child =>
                ingest(collections, pagers, child, workspaceId, c.id)
            );
        }
    }
}

export const useCollectionStore = create<State>((set, get) => ({
    workspaces: [],
    workspacesLoaded: false,
    workspacesLoading: false,
    collections: {},
    pagers: {},

    loadWorkspaces: async force => {
        if ((get().workspacesLoaded && !force) || get().workspacesLoading) {
            return;
        }
        set({workspacesLoading: true});
        try {
            const page = await getWorkspaces();
            set({workspaces: page.items, workspacesLoaded: true});
        } finally {
            set({workspacesLoading: false});
        }
    },

    upsertWorkspace: workspace =>
        set(s => ({
            workspaces: s.workspaces.some(w => w.id === workspace.id)
                ? s.workspaces.map(w => (w.id === workspace.id ? workspace : w))
                : [...s.workspaces, workspace],
        })),

    loadChildren: async (workspaceId, parentId, force) => {
        const key = pagerKey(workspaceId, parentId);
        const pager = get().pagers[key];
        if (pager?.loading || (pager?.loaded && !force)) {
            return;
        }
        set(s => ({
            pagers: {
                ...s.pagers,
                [key]: {...(pager ?? {ids: [], loaded: false}), loading: true},
            },
        }));
        try {
            const page = await getCollections({
                workspaces: [workspaceId],
                parent: parentId,
                limit: collectionPageLimit,
                childrenLimit: collectionChildrenLimit,
            });
            set(s => {
                const collections = {...s.collections};
                const pagers = {...s.pagers};
                page.items.forEach(c =>
                    ingest(collections, pagers, c, workspaceId, parentId)
                );
                // keep locally created collections not yet indexed
                const previous = pagers[key]?.ids ?? [];
                const ids = page.items.map(c => c.id);
                previous.forEach(id => {
                    if (!ids.includes(id) && collections[id] && !page.next) {
                        ids.push(id);
                    }
                });
                pagers[key] = {
                    ids,
                    total: page.total,
                    next: page.next,
                    loading: false,
                    loaded: true,
                };

                return {collections, pagers};
            });
        } catch (e) {
            set(s => ({
                pagers: {
                    ...s.pagers,
                    [key]: {
                        ...(s.pagers[key] ?? {ids: [], loaded: false}),
                        loading: false,
                    },
                },
            }));
            throw e;
        }
    },

    loadMore: async (workspaceId, parentId) => {
        const key = pagerKey(workspaceId, parentId);
        const pager = get().pagers[key];
        if (!pager?.next || pager.loading) {
            return;
        }
        set(s => ({pagers: {...s.pagers, [key]: {...pager, loading: true}}}));
        const page = await getCollections({url: pager.next});
        set(s => {
            const collections = {...s.collections};
            const pagers = {...s.pagers};
            page.items.forEach(c =>
                ingest(collections, pagers, c, workspaceId, parentId)
            );
            pagers[key] = {
                ...pagers[key],
                ids: [
                    ...pagers[key].ids,
                    ...page.items
                        .map(c => c.id)
                        .filter(id => !pagers[key].ids.includes(id)),
                ],
                next: page.next,
                total: page.total,
                loading: false,
            };

            return {collections, pagers};
        });
    },

    loadAscendants: async collectionId => {
        const root = await getCollectionAscendants(collectionId);
        const workspaceId = root.workspace.id;
        set(s => {
            const collections = {...s.collections};
            const pagers = {...s.pagers};
            const walk = (c: Collection, parentId?: string) => {
                collections[c.id] = {
                    ...collections[c.id],
                    ...c,
                    children: undefined,
                    workspaceId,
                    parentId,
                };
                c.children?.forEach(child => walk(child, c.id));
            };
            walk(root);

            return {collections, pagers};
        });

        return root;
    },

    upsertCollection: (collection, workspaceId) =>
        set(s => {
            const wsId =
                workspaceId ??
                collection.workspace?.id ??
                s.collections[collection.id]?.workspaceId;
            if (!wsId) {
                return {};
            }
            const parentId = collection.parentId ?? collection.parent?.id;
            const collections = {...s.collections};
            const pagers = {...s.pagers};
            const isNew = !collections[collection.id];
            collections[collection.id] = {
                ...collections[collection.id],
                ...collection,
                workspaceId: wsId,
                parentId,
            };
            if (isNew) {
                const key = pagerKey(wsId, parentId);
                const pager = pagers[key] ?? {
                    ids: [],
                    loading: false,
                    loaded: false,
                };
                if (!pager.ids.includes(collection.id)) {
                    pagers[key] = {
                        ...pager,
                        ids: [...pager.ids, collection.id],
                        total: (pager.total ?? pager.ids.length) + 1,
                    };
                }
            }

            return {collections, pagers};
        }),

    patchCollections: (ids, patch) =>
        set(s => {
            const collections = {...s.collections};
            ids.forEach(id => {
                if (collections[id]) {
                    collections[id] = {...collections[id], ...patch};
                }
            });

            return {collections};
        }),

    removeCollection: id =>
        set(s => {
            const node = s.collections[id];
            if (!node) {
                return {};
            }
            const collections = {...s.collections};
            delete collections[id];
            const key = pagerKey(node.workspaceId, node.parentId);
            const pagers = {...s.pagers};
            if (pagers[key]) {
                pagers[key] = {
                    ...pagers[key],
                    ids: pagers[key].ids.filter(i => i !== id),
                };
            }

            return {collections, pagers};
        }),

    moveCollection: (id, newParentId) =>
        set(s => {
            const node = s.collections[id];
            if (!node) {
                return {};
            }
            const pagers = {...s.pagers};
            const oldKey = pagerKey(node.workspaceId, node.parentId);
            const newKey = pagerKey(node.workspaceId, newParentId);
            if (pagers[oldKey]) {
                pagers[oldKey] = {
                    ...pagers[oldKey],
                    ids: pagers[oldKey].ids.filter(i => i !== id),
                };
            }
            const target = pagers[newKey] ?? {
                ids: [],
                loading: false,
                loaded: false,
            };
            pagers[newKey] = {...target, ids: [...target.ids, id]};

            return {
                pagers,
                collections: {
                    ...s.collections,
                    [id]: {...node, parentId: newParentId},
                },
            };
        }),
}));

export {pagerKey};

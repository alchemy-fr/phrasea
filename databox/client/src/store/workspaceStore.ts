import {create} from 'zustand';
import {Workspace} from '../types';
import {getWorkspace, getWorkspaces} from '../api/workspace.ts';

type State = {
    tree: Record<string, Workspace>;
    workspaces: Workspace[];
    getWorkspace: (id: string) => Promise<Workspace> | undefined;
    updateWorkspace: (workspace: Workspace) => void;
    partialUpdateWorkspace: (id: string, updates: Partial<Workspace>) => void;
    load: () => Promise<void>;
    loaded: boolean;
    loading: boolean;
    loadingMore: boolean;
    total?: number;
};

export const useWorkspaceStore = create<State>((set, getState) => ({
    workspaces: [],
    tree: {},
    loading: false,
    loaded: false,
    loadingMore: false,
    total: undefined,

    load: async (force?: boolean) => {
        if (!force) {
            const current = getState();
            if (current.loaded || current.loading) {
                return;
            }
        }

        set({loading: true});
        try {
            const data = await getWorkspaces();

            set(state => {
                const tree = {...state.tree};

                data.result.forEach(w => {
                    tree[w.id] = w;
                });

                return {
                    tree,
                    workspaces: data.result,
                    loaded: true,
                };
            });
        } finally {
            set({loading: false});
        }
    },

    updateWorkspace: workspace => {
        getState().partialUpdateWorkspace(workspace.id, workspace);
    },

    getWorkspace: async id => {
        const state = getState();

        const w = state.tree[id];
        if (w) {
            return w;
        }

        return getWorkspace(id).then(w => {
            state.updateWorkspace(w);

            return w;
        });
    },

    partialUpdateWorkspace: (id, updates) => {
        set(state => {
            const tree = {...state.tree};
            const workspaces = [...state.workspaces];

            tree[id] = {
                ...(tree[id] ?? {}),
                ...updates,
            };

            const idx = workspaces.findIndex(w => w.id === id);
            if (idx >= 0) {
                workspaces[idx] = {
                    ...workspaces[idx],
                    ...updates,
                };
            }

            return {
                workspaces,
                tree,
            };
        });
    },
}));

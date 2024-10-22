import {create} from 'zustand';
import {Workspace} from '../types';
import {getWorkspaces} from '../api/workspace.ts';

type State = {
    tree: Record<string, Workspace>;
    workspaces: Workspace[];
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

    partialUpdateWorkspace: (id, updates) => {
        set(state => {
            const tree = {...state.tree};

            tree[id] = {
                ...(tree[id] ?? {}),
                ...updates,
            };

            return {
                tree,
            };
        });
    },
}));

import {useWorkspaceStore} from '../store/workspaceStore.ts';
import {Workspace} from '../types.ts';

export function useWorkspace(
    workspaceId: string | undefined
): Workspace | undefined {
    const tree = useWorkspaceStore(s => s.tree);
    const getWorkspace = useWorkspaceStore(s => s.getWorkspace);

    if (!workspaceId) {
        return;
    }

    if (tree[workspaceId]) {
        return tree[workspaceId];
    }

    getWorkspace(workspaceId);
}

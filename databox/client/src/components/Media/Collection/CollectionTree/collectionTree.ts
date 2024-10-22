import {CollectionOptionalWorkspace} from "../../../../types.ts";
import {nodeNewPrefix} from "../EditableTree.tsx";

const nodeSeparator = '|';

export {nodeSeparator as treeViewPathSeparator};

export type SetExpanded = (
    nodeIds: string[] | ((prevNodeIds: string[]) => string[])
) => void;

export type UpdateCollectionPath = (
    index: number,
    id: string | null,
    value?: string | null,
    editing?: boolean
) => void;

export type NewCollectionPath = {
    rootId: string;
    path: string[];
};

export type CommonTreeItemProps<IsMulti extends boolean = false> = {
    isSelectable?: IsSelectable;
    newCollectionPath: NewCollectionPathState | undefined;
    setNewCollectionPath: SetNewCollectionPath;
    updateCollectionPath: UpdateCollectionPath;
    setExpanded: SetExpanded;
} & CommonTreeProps<IsMulti>;


export type CommonTreeProps<IsMulti extends boolean = false> = {
    multiple?: IsMulti;
    disabledBranches?: string[];
    allowNew?: boolean;
    disabled?: boolean | undefined;
    isSelectable?: IsSelectable;
}

export type CollectionId = string;

export type Collection = CollectionId | NewCollectionPath;

export type IsSelectable = (collection: CollectionOptionalWorkspace) => boolean;

export function normalizeNodeId(
    nodeId: string,
    newCollectionPath: NewCollectionPathState | undefined
): Collection {
    if (newCollectionPath && nodeId.startsWith(nodeNewPrefix)) {
        const offset = parseInt(nodeId.substring(nodeNewPrefix.length));

        return {
            rootId: newCollectionPath.rootNode,
            path: new Array(offset + 1)
                .fill(true, 0, offset + 1)
                .map((_, i) => newCollectionPath.nodes[i].value),
        };
    }

    return nodeId.split(nodeSeparator)[1];
}

export type NewCollectionNodeState = {
    id: string;
    value: string;
    editing?: boolean | undefined;
};

export type NewCollectionPathState = {
    rootNode: string;
    nodes: NewCollectionNodeState[];
};

export type SetNewCollectionPath = (
    nodes: NewCollectionNodeState[],
    rootId?: string
) => void;

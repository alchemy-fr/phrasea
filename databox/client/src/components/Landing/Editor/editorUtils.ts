import {Editor} from '@tiptap/core';
import {NodeSelection} from '@tiptap/pm/state';

export function isNodeTypeSelected(
    editor: Editor | null,
    nodeTypeNames: string[] = [],
    checkAncestorNodes: boolean = false
): boolean {
    if (!editor || !editor.state.selection) return false;

    const {selection} = editor.state;
    if (selection.empty) return false;

    // Direct node selection check
    if (selection instanceof NodeSelection) {
        const selectedNode = selection.node;
        return selectedNode
            ? nodeTypeNames.includes(selectedNode.type.name)
            : false;
    }

    // Depth-based ancestor node check
    if (checkAncestorNodes) {
        const {$from} = selection;
        for (let depth = $from.depth; depth > 0; depth--) {
            const ancestorNode = $from.node(depth);
            if (nodeTypeNames.includes(ancestorNode.type.name)) {
                return true;
            }
        }
    }

    return false;
}

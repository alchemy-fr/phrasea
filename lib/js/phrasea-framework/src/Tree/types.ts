import {FC, ReactNode} from 'react';

export type TreeBaseItem = {};

type BaseTreeNode<D extends TreeBaseItem> = {
    id: string;
    data: D;
    // If children is undefined, it means they are not loaded yet
    children?: TreeNode<D>[] | undefined;
    parentId?: string;
    hasChildren: boolean;
    childrenLoaded?: boolean;
    loadingChildren?: boolean;
    loadingMoreChildren?: boolean;
    nextCursor?: string; // If partially loaded, the cursor for loading more children
    canEdit?: boolean;
    editing?: boolean;
    editedOnce?: boolean; // State to know if the node was added and confirmed
    canDelete?: boolean;
    canAddChildren?: boolean;
};

export type ConcreteTreeNode<D extends TreeBaseItem> = {
    virtual?: never;
    parentNode?: never;
} & BaseTreeNode<D>;

export type VirtualTreeNode<D extends TreeBaseItem> = {
    virtual: true;
    parentNode: TreeNode<D>;
} & BaseTreeNode<D>;

export type TreeNode<D extends TreeBaseItem> =
    | ConcreteTreeNode<D>
    | VirtualTreeNode<D>;

export type RenderNodeProps<D extends TreeBaseItem> = {
    level: number;
    node: TreeNode<D>;
};

export type RenderNodeLabel<D extends TreeBaseItem> = (
    props: RenderNodeProps<D>
) => ReactNode;

export type RenderNodeEdit<D extends TreeBaseItem> = (
    node: TreeNode<D>,
) => ReactNode;

export type OnToggleSelectNode<D extends TreeBaseItem> = (
    node: TreeNode<D>,
    selected: boolean
) => void;

export type OnToggleExpand<D extends TreeBaseItem> = (
    node: TreeNode<D>,
    expended: boolean
) => Promise<void>;

export type LoadNodeChildren<D extends TreeBaseItem> = (
    node: TreeNode<D>
) => Promise<void>;

export type OnNodeAdd<D extends TreeBaseItem> = (
    parentNode: TreeNode<D>,
    node: Partial<TreeNode<D>>
) => void;

export type OnNodeRemove<D extends TreeBaseItem> = (
    node: TreeNode<D>
) => void;

export type OnNodeUpdate<D extends TreeBaseItem> = (
    oldNode: Partial<TreeNode<D>>,
    newNode: Partial<TreeNode<D>>,
) => void;

export type OnNodeStartEdit<D extends TreeBaseItem> = (
    node: TreeNode<D>,
) => void;

type CommonTreeProps<D extends TreeBaseItem> = {
    renderNodeLabel: RenderNodeLabel<D>;
} & CommonTreeOptionsProps<D>;

export type TreeNodeEditComponentProps<D extends TreeBaseItem> = {
    onFinishEdit: (data: D) => void;
    onCancelEdit: () => void;
} & TreeNodeProps<D>;

export type EditionProps<D extends TreeBaseItem> = {
    onNodeAdd?: OnNodeAdd<D>;
    onNodeRemove?: OnNodeRemove<D>;
    onNodeUpdate?: OnNodeUpdate<D>;
    onNodeCancelEdit?: OnNodeStartEdit<D>;
    onNodeStartEdit?: OnNodeStartEdit<D>;
};

type CommonTreeOptionsProps<D extends TreeBaseItem> = {
    disabled?: boolean;
    disabledBranches?: string[];
    editNodeComponent?: FC<TreeNodeEditComponentProps<D>>;
} & EditionProps<D>;

type IsSelectable<D extends TreeBaseItem> = (node: TreeNode<D>) => boolean;

export type TreeViewOptionsProps<D extends TreeBaseItem> = {
    onToggleExpand?: OnToggleExpand<D>;
    onToggleSelect?: OnToggleSelectNode<D>;
    defaultExpandedNodes?: string[];
    defaultSelectedNodes?: string[];
    selectShouldExpand?: boolean;
    selectShouldCollapse?: boolean;
    collapseShouldUnselectChildren?: boolean;
    loadChildren?: LoadNodeChildren<D>;
    isSelectable?: IsSelectable<D>;
    loadMoreChildren?: (
        node: TreeNode<D>,
        cursor: string
    ) => Promise<TreeNode<D>[]>;
    multiple?: boolean;
    required?: boolean;
} & CommonTreeOptionsProps<D>;

export type TreeViewProps<D extends TreeBaseItem> = {
    nodes: TreeNode<D>[];
} & TreeViewOptionsProps<D> &
    CommonTreeProps<D>;

export type TreeNodeProps<D extends TreeBaseItem> = {
    node: TreeNode<D>;
    level: number;
    selectedNodes: string[];
    expandedNodes: string[];
    onToggleExpand: OnToggleExpand<D>;
    onToggleSelect: OnToggleSelectNode<D>;
} & CommonTreeProps<D>;

export enum TreeViewClasses {
    Node = 'TreeView-Node',
    NodeSelected = 'TreeView-NodeSelected',
    NodeArrow = 'TreeView-NodeArrow',
    NodeLabel = 'TreeView-NodeLabel',
    NodeExpanded = 'TreeView-NodeExpanded',
    NodeDisabled = 'TreeView-NodeDisabled',
    NodeChildren = 'TreeView-NodeChildren',
}

export type VirtualNodes<D extends TreeBaseItem> = VirtualTreeNode<D>[];

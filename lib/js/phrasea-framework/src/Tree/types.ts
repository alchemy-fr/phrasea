import React, {ReactNode, SyntheticEvent} from 'react';

export type TreeBaseItem = {};

type BaseTreeEvent<D extends TreeBaseItem> = {
    id: string;
    data: D;
    // If children is undefined, it means they are not loaded yet
    children?: TreeNode<D>[] | undefined;
    hasChildren: boolean;
    loadingChildren?: boolean;
    loadingMoreChildren?: boolean;
    nextCursor?: string; // If partially loaded, the cursor for loading more children
    canEdit?: boolean;
    canDelete?: boolean;
    canAddChildren?: boolean;
};

export type ConcreteTreeNode<D extends TreeBaseItem> = {
    virtual?: never;
    parentNode?: never;
} & BaseTreeEvent<D>;

export type VirtualTreeNode<D extends TreeBaseItem> = {
    virtual: true;
    parentNode?: TreeNode<D>;
} & BaseTreeEvent<D>;

export type TreeNode<D extends TreeBaseItem> =
    | ConcreteTreeNode<D>
    | VirtualTreeNode<D>;

export type RenderNodeProps<D extends TreeBaseItem> = {
    level: number;
    node: TreeNode<D>;
};

export type RenderNodeLabel<D extends TreeBaseItem> = (props: RenderNodeProps<D>) => ReactNode;

export type OnToggleSelectNode<D extends TreeBaseItem> = (
    node: TreeNode<D>,
    selected: boolean,
) => void;

export type OnToggleExpand<D extends TreeBaseItem> = (
    node: TreeNode<D>,
    expended: boolean,
) => Promise<void>;

export type LoadNodeChildren<D extends TreeBaseItem> = (node: TreeNode<D>) => Promise<void>;

export type OnNodeAdd<D extends TreeBaseItem> = (
    parentNode: TreeNode<D>,
    node: Partial<TreeNode<D>>,
) => void;

type CommonTreeProps<D extends TreeBaseItem> = {
    renderNodeLabel: RenderNodeLabel<D>;
} & CommonTreeOptionsProps<D>;

type CommonTreeOptionsProps<D extends TreeBaseItem> = {
    disabled?: boolean;
    disabledBranches?: string[];
    onNodeAdd?: OnNodeAdd<D>;
};

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
} & TreeViewOptionsProps<D> & CommonTreeProps<D>;

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

export type VirtualNodes<D extends TreeBaseItem> = Record<string, TreeNode<D>[]>;

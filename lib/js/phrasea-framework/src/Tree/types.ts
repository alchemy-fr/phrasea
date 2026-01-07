import {ReactNode} from 'react';

export type NavigationNode<D extends {}> = {
    id: string;
    data: D;
    selected?: boolean;
    expanded?: boolean;
    children?: NavigationNode<D>[];
    hasChildren: boolean;
    childrenLoaded: boolean;
    loadingChildren?: boolean;
    loadingMoreChildren?: boolean;
    nextCursor?: string; // If partially loaded, the cursor for loading more children
};

export type NavigationRootNode<D extends {}> = Omit<NavigationNode<D>, "data" | "id">;

export type RenderItemProps<D extends {}> = {
    data: D;
    level: number;
    item: NavigationNode<D>;
};

export type RenderItem<D extends {}> = (props: RenderItemProps<D>) => ReactNode;

export type NavigationTreeProps<D extends {}> = {
    renderItem: RenderItem<D>;
    rootItem: NavigationRootNode<D>;
    loadChildren?: (nodeId: string) => Promise<NavigationNode<D>>;
    loadMoreChildren?: (
        nodeId: string,
        cursor: string
    ) => Promise<NavigationNode<D>[]>;
};

export type TreeNodeProps<D extends {}> = {
    item: NavigationNode<D>;
    renderItem: RenderItem<D>;
    level: number;
};

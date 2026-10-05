import type {Asset, Basket, Workspace} from '@/types/api';
import type {CollectionNode} from '@/features/collections/collectionStore';
import type {LeftPanelTab} from '@/components/layout/layoutStore';

/** What a drop of assets does to them */
export type AssetOp = 'add' | 'move' | 'copy';

export type DropOp = AssetOp | 'move-collection';

/** Modifier keys held while dragging: Shift moves, Ctrl (Cmd) duplicates */
export type DragModifiers = {shift: boolean; ctrl: boolean};

export const noModifiers: DragModifiers = {shift: false, ctrl: false};

/** What is being dragged, resolved at drag start */
export type DragPayload =
    | {
          type: 'assets';
          assets: Asset[];
          /** The list the drag started from */
          source: {scope: string; basketId?: string};
      }
    | {type: 'collection'; collection: CollectionNode};

/** What a droppable element stands for */
export type DropTarget =
    | {type: 'collection'; collection: CollectionNode}
    | {type: 'workspace'; workspace: Workspace}
    | {type: 'basket'; basket: Basket}
    | {type: 'story'; story: Asset}
    /** Sidebar tab trigger: hovering it switches the tab, dropping does nothing */
    | {type: 'tab'; tab: LeftPanelTab}
    /** A whole sidebar panel: keeps auto-scroll going between rows */
    | {type: 'panel'; id: string};

export type DropReason =
    | 'type'
    | 'workspace'
    | 'capability'
    | 'self'
    | 'descendant'
    | 'already'
    | 'deleted';

export type DropVerdict =
    | {ok: true; op: DropOp}
    | {ok: false; reason: DropReason};

/** Registered by an asset card: resolved into a `DragPayload` at drag start */
export type AssetDragSource = {
    kind: 'asset-source';
    asset: Asset;
    scope: string;
    basketId?: string;
    /** The list's selection, read when the drag starts */
    getSelection: () => Asset[];
};

export type CollectionDragSource = {
    kind: 'collection-source';
    collection: CollectionNode;
};

export type DragSource = AssetDragSource | CollectionDragSource;

export const dragId = {
    asset: (scope: string, id: string) => `asset:${scope}:${id}`,
    collection: (id: string) => `collection-drag:${id}`,
};

export function dropId(target: DropTarget): string {
    switch (target.type) {
        case 'collection':
            return `collection:${target.collection.id}`;
        case 'workspace':
            return `workspace:${target.workspace.id}`;
        case 'basket':
            return `basket:${target.basket.id}`;
        case 'story':
            return `story:${target.story.id}`;
        case 'tab':
            return `tab:${target.tab}`;
        case 'panel':
            return `panel:${target.id}`;
    }
}

export function targetName(target: DropTarget): string {
    switch (target.type) {
        case 'collection':
            return (
                target.collection.displayName ?? target.collection.name ?? ''
            );
        case 'workspace':
            return target.workspace.displayName ?? target.workspace.name;
        case 'basket':
            return target.basket.name;
        case 'story':
            return target.story.name ?? '';
        default:
            return '';
    }
}

/** Targets that are never dropped on: no validity feedback for them */
export function isNeutralTarget(target: DropTarget): boolean {
    return target.type === 'tab' || target.type === 'panel';
}

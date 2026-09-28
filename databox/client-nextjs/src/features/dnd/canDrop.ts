import type {Asset} from '@/types/api';
import type {CollectionNode} from '@/features/collections/collectionStore';
import {
    type AssetOp,
    type DragModifiers,
    type DragPayload,
    type DropTarget,
    type DropVerdict,
    noModifiers,
} from './types';

export type CanDropContext = {
    /** The collection tree, to walk up parents */
    collections: Record<string, CollectionNode>;
    modifiers?: DragModifiers;
};

/** Whether `id` is `ancestorId` or one of its descendants */
export function isDescendant(
    collections: Record<string, CollectionNode>,
    ancestorId: string,
    id: string
): boolean {
    const seen = new Set<string>();
    let current: string | undefined = id;
    while (current && !seen.has(current)) {
        if (current === ancestorId) {
            return true;
        }
        seen.add(current);
        current = collections[current]?.parentId;
    }

    return false;
}

/** The operation asked for by the modifier keys */
export function modifiersToOp(modifiers: DragModifiers): AssetOp {
    if (modifiers.ctrl) {
        return 'copy';
    }
    if (modifiers.shift) {
        return 'move';
    }

    return 'add';
}

function workspaceOf(asset: Asset): string | undefined {
    return asset.workspace?.id;
}

function collectionWorkspace(collection: CollectionNode): string | undefined {
    return collection.workspaceId ?? collection.workspace?.id;
}

/**
 * Tells whether a payload can be dropped on a target, and what it would do.
 * Pure: the same rules are enforced by the API, they only shape the feedback
 * and avoid pointless requests.
 */
export function canDrop(
    payload: DragPayload,
    target: DropTarget,
    {collections, modifiers = noModifiers}: CanDropContext
): DropVerdict {
    if (payload.type === 'assets') {
        return canDropAssets(payload.assets, payload.source, target, modifiers);
    }

    return canDropCollection(payload.collection, target, collections);
}

function canDropAssets(
    assets: Asset[],
    source: {basketId?: string},
    target: DropTarget,
    modifiers: DragModifiers
): DropVerdict {
    if (assets.length === 0) {
        return {ok: false, reason: 'type'};
    }
    let op = modifiersToOp(modifiers);
    const workspaces = new Set(assets.map(workspaceOf));
    const crossWorkspace = (workspaceId: string | undefined) =>
        [...workspaces].some(w => w !== workspaceId);

    switch (target.type) {
        case 'collection': {
            const {collection} = target;
            if (collection.deleted) {
                return {ok: false, reason: 'deleted'};
            }
            if (!collection.capabilities.createAsset) {
                return {ok: false, reason: 'capability'};
            }
            if (assets.some(a => a.storyCollection?.id === collection.id)) {
                return {ok: false, reason: 'self'};
            }
            if (crossWorkspace(collectionWorkspace(collection))) {
                // Assets never leave their workspace
                if (op === 'move') {
                    return {ok: false, reason: 'workspace'};
                }
                // Assets cannot be linked across workspaces: they get duplicated
                if (op === 'add') {
                    op = 'copy';
                }
            }
            if (
                op === 'add' &&
                assets.every(a =>
                    a.collections?.some(c => c.id === collection.id)
                )
            ) {
                return {ok: false, reason: 'already'};
            }

            return {ok: true, op};
        }
        case 'story': {
            const {story} = target;
            const storyCollection = story.storyCollection;
            if (!storyCollection) {
                return {ok: false, reason: 'type'};
            }
            if (story.deleted) {
                return {ok: false, reason: 'deleted'};
            }
            if (
                storyCollection.capabilities &&
                !storyCollection.capabilities.createAsset
            ) {
                return {ok: false, reason: 'capability'};
            }
            if (assets.some(a => a.id === story.id)) {
                return {ok: false, reason: 'self'};
            }
            if (crossWorkspace(workspaceOf(story))) {
                return {ok: false, reason: 'workspace'};
            }
            if (
                op === 'add' &&
                assets.every(a =>
                    a.collections?.some(c => c.id === storyCollection.id)
                )
            ) {
                return {ok: false, reason: 'already'};
            }

            return {ok: true, op};
        }
        case 'basket': {
            const {basket} = target;
            if (basket.isArchived) {
                return {ok: false, reason: 'deleted'};
            }
            if (!basket.capabilities.edit) {
                return {ok: false, reason: 'capability'};
            }
            if (source.basketId === basket.id) {
                return {ok: false, reason: 'already'};
            }

            return {ok: true, op: 'add'};
        }
        case 'workspace': {
            const {workspace} = target;
            // Nothing to link at the root of the assets' own workspace
            if (!crossWorkspace(workspace.id)) {
                return {ok: false, reason: 'already'};
            }
            if (!workspace.capabilities.createAsset) {
                return {ok: false, reason: 'capability'};
            }

            return {ok: true, op: 'copy'};
        }
        default:
            return {ok: false, reason: 'type'};
    }
}

function canDropCollection(
    source: CollectionNode,
    target: DropTarget,
    collections: Record<string, CollectionNode>
): DropVerdict {
    switch (target.type) {
        case 'collection': {
            const {collection} = target;
            if (
                collectionWorkspace(collection) !== collectionWorkspace(source)
            ) {
                return {ok: false, reason: 'workspace'};
            }
            if (collection.id === source.id) {
                return {ok: false, reason: 'self'};
            }
            if (isDescendant(collections, source.id, collection.id)) {
                return {ok: false, reason: 'descendant'};
            }
            if (collection.deleted) {
                return {ok: false, reason: 'deleted'};
            }
            if (source.parentId === collection.id) {
                return {ok: false, reason: 'already'};
            }
            if (
                !source.capabilities.edit ||
                !collection.capabilities.createCollection
            ) {
                return {ok: false, reason: 'capability'};
            }

            return {ok: true, op: 'move-collection'};
        }
        case 'workspace': {
            const {workspace} = target;
            if (workspace.id !== collectionWorkspace(source)) {
                return {ok: false, reason: 'workspace'};
            }
            if (!source.parentId) {
                return {ok: false, reason: 'already'};
            }
            if (
                !source.capabilities.edit ||
                !workspace.capabilities.createCollection
            ) {
                return {ok: false, reason: 'capability'};
            }

            return {ok: true, op: 'move-collection'};
        }
        default:
            return {ok: false, reason: 'type'};
    }
}

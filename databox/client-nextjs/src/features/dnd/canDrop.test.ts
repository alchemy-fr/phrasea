import {describe, expect, it} from 'vitest';
import type {Asset, Basket, Workspace} from '@/types/api';
import type {CollectionNode} from '@/features/collections/collectionStore';
import {canDrop, isDescendant} from './canDrop';
import type {DragPayload, DropTarget} from './types';

const ws = (id: string, caps = {createAsset: true, createCollection: true}) =>
    ({
        id,
        name: id,
        displayName: id,
        capabilities: caps,
    }) as unknown as Workspace;

const col = (
    id: string,
    workspaceId: string,
    extra: Partial<CollectionNode> = {}
) =>
    ({
        id,
        name: id,
        displayName: id,
        workspaceId,
        capabilities: {
            createAsset: true,
            createCollection: true,
            edit: true,
            delete: true,
        },
        ...extra,
    }) as unknown as CollectionNode;

const asset = (id: string, workspaceId: string, extra: Partial<Asset> = {}) =>
    ({
        id,
        name: id,
        workspace: {id: workspaceId},
        capabilities: {},
        ...extra,
    }) as unknown as Asset;

const basket = (id: string, extra: Partial<Basket> = {}) =>
    ({
        id,
        name: id,
        isArchived: false,
        capabilities: {edit: true, delete: true, share: true},
        ...extra,
    }) as unknown as Basket;

// ws1: root > child > grandchild ; other (root)
const collections: Record<string, CollectionNode> = {
    root: col('root', 'ws1'),
    child: col('child', 'ws1', {parentId: 'root'}),
    grandchild: col('grandchild', 'ws1', {parentId: 'child'}),
    other: col('other', 'ws1'),
    foreign: col('foreign', 'ws2'),
};

const assetsPayload = (assets: Asset[], basketId?: string): DragPayload => ({
    type: 'assets',
    assets,
    source: {scope: 's', basketId},
});
const collectionPayload = (id: string): DragPayload => ({
    type: 'collection',
    collection: collections[id],
});
const target = (t: DropTarget) => t;

const verdict = (
    payload: DragPayload,
    t: DropTarget,
    modifiers?: {shift?: boolean; ctrl?: boolean}
) =>
    canDrop(payload, t, {
        collections,
        modifiers: {shift: false, ctrl: false, ...modifiers},
    });

describe('isDescendant', () => {
    it('walks up the parents', () => {
        expect(isDescendant(collections, 'root', 'grandchild')).toBe(true);
        expect(isDescendant(collections, 'root', 'root')).toBe(true);
        expect(isDescendant(collections, 'child', 'root')).toBe(false);
        expect(isDescendant(collections, 'root', 'other')).toBe(false);
    });
});

describe('canDrop assets', () => {
    const a1 = asset('a1', 'ws1');
    const a2 = asset('a2', 'ws1');

    it('adds to a collection by default, moves with Shift, duplicates with Ctrl', () => {
        const t = target({type: 'collection', collection: collections.root});
        expect(verdict(assetsPayload([a1, a2]), t)).toEqual({
            ok: true,
            op: 'add',
        });
        expect(verdict(assetsPayload([a1]), t, {shift: true})).toEqual({
            ok: true,
            op: 'move',
        });
        expect(verdict(assetsPayload([a1]), t, {ctrl: true})).toEqual({
            ok: true,
            op: 'copy',
        });
        expect(
            verdict(assetsPayload([a1]), t, {ctrl: true, shift: true})
        ).toEqual({ok: true, op: 'copy'});
    });

    it('duplicates instead of linking across workspaces', () => {
        const t = target({type: 'collection', collection: collections.foreign});
        expect(verdict(assetsPayload([a1]), t)).toEqual({ok: true, op: 'copy'});
        expect(verdict(assetsPayload([a1]), t, {ctrl: true})).toEqual({
            ok: true,
            op: 'copy',
        });
    });

    it('refuses to move assets to another workspace', () => {
        const t = target({type: 'collection', collection: collections.foreign});
        expect(verdict(assetsPayload([a1]), t, {shift: true})).toEqual({
            ok: false,
            reason: 'workspace',
        });
    });

    it('refuses a collection without the capability, deleted, or already holding all the assets', () => {
        expect(
            verdict(
                assetsPayload([a1]),
                target({
                    type: 'collection',
                    collection: col('c', 'ws1', {
                        capabilities: {createAsset: false} as any,
                    }),
                })
            )
        ).toEqual({ok: false, reason: 'capability'});
        expect(
            verdict(
                assetsPayload([a1]),
                target({
                    type: 'collection',
                    collection: col('c', 'ws1', {deleted: true}),
                })
            )
        ).toEqual({ok: false, reason: 'deleted'});
        const inRoot = asset('a3', 'ws1', {
            collections: [{id: 'root'} as any],
        });
        const t = target({type: 'collection', collection: collections.root});
        expect(verdict(assetsPayload([inRoot]), t)).toEqual({
            ok: false,
            reason: 'already',
        });
        // Not all of them: still something to add
        expect(verdict(assetsPayload([inRoot, a1]), t)).toEqual({
            ok: true,
            op: 'add',
        });
        // Moving out of and into the same collection is a no-op, but a
        // duplication is not
        expect(verdict(assetsPayload([inRoot]), t, {ctrl: true})).toEqual({
            ok: true,
            op: 'copy',
        });
    });

    it('refuses a story dropped on its own collection', () => {
        const story = asset('story', 'ws1', {
            storyCollection: {id: 'sc'} as any,
        });
        const t = target({type: 'collection', collection: col('sc', 'ws1')});
        expect(verdict(assetsPayload([story]), t)).toEqual({
            ok: false,
            reason: 'self',
        });
    });

    it('adds to a story', () => {
        const story = asset('story', 'ws1', {
            storyCollection: {
                id: 'sc',
                capabilities: {createAsset: true},
            } as any,
        });
        const t = target({type: 'story', story});
        expect(verdict(assetsPayload([a1]), t)).toEqual({ok: true, op: 'add'});
        expect(verdict(assetsPayload([story]), t)).toEqual({
            ok: false,
            reason: 'self',
        });
        expect(verdict(assetsPayload([asset('x', 'ws2')]), t)).toEqual({
            ok: false,
            reason: 'workspace',
        });
        expect(
            verdict(assetsPayload([a1]), target({type: 'story', story: a2}))
        ).toEqual({ok: false, reason: 'type'});
    });

    it('adds to a basket, whatever the modifiers', () => {
        const t = target({type: 'basket', basket: basket('b')});
        expect(verdict(assetsPayload([a1]), t, {shift: true})).toEqual({
            ok: true,
            op: 'add',
        });
        expect(verdict(assetsPayload([a1], 'b'), t)).toEqual({
            ok: false,
            reason: 'already',
        });
        expect(
            verdict(
                assetsPayload([a1]),
                target({
                    type: 'basket',
                    basket: basket('b', {isArchived: true}),
                })
            )
        ).toEqual({ok: false, reason: 'deleted'});
        expect(
            verdict(
                assetsPayload([a1]),
                target({
                    type: 'basket',
                    basket: basket('b', {capabilities: {edit: false} as any}),
                })
            )
        ).toEqual({ok: false, reason: 'capability'});
    });

    it('duplicates into another workspace only', () => {
        expect(
            verdict(
                assetsPayload([a1]),
                target({type: 'workspace', workspace: ws('ws1')})
            )
        ).toEqual({ok: false, reason: 'already'});
        expect(
            verdict(
                assetsPayload([a1]),
                target({type: 'workspace', workspace: ws('ws2')})
            )
        ).toEqual({ok: true, op: 'copy'});
        expect(
            verdict(
                assetsPayload([a1]),
                target({
                    type: 'workspace',
                    workspace: ws('ws2', {
                        createAsset: false,
                        createCollection: true,
                    }),
                })
            )
        ).toEqual({ok: false, reason: 'capability'});
    });

    it('never drops on a tab or a panel', () => {
        expect(
            verdict(assetsPayload([a1]), target({type: 'tab', tab: 'tree'}))
        ).toEqual({
            ok: false,
            reason: 'type',
        });
        expect(
            verdict(assetsPayload([a1]), target({type: 'panel', id: 'tree'}))
        ).toEqual({
            ok: false,
            reason: 'type',
        });
    });
});

describe('canDrop collection', () => {
    it('moves into another collection of the workspace', () => {
        expect(
            verdict(
                collectionPayload('other'),
                target({type: 'collection', collection: collections.child})
            )
        ).toEqual({ok: true, op: 'move-collection'});
    });

    it('refuses itself, its descendants and its current parent', () => {
        expect(
            verdict(
                collectionPayload('root'),
                target({type: 'collection', collection: collections.root})
            )
        ).toEqual({ok: false, reason: 'self'});
        expect(
            verdict(
                collectionPayload('root'),
                target({type: 'collection', collection: collections.grandchild})
            )
        ).toEqual({ok: false, reason: 'descendant'});
        expect(
            verdict(
                collectionPayload('child'),
                target({type: 'collection', collection: collections.root})
            )
        ).toEqual({ok: false, reason: 'already'});
        expect(
            verdict(
                collectionPayload('root'),
                target({type: 'collection', collection: collections.foreign})
            )
        ).toEqual({ok: false, reason: 'workspace'});
    });

    it('moves to the root of its workspace', () => {
        expect(
            verdict(
                collectionPayload('child'),
                target({type: 'workspace', workspace: ws('ws1')})
            )
        ).toEqual({ok: true, op: 'move-collection'});
        expect(
            verdict(
                collectionPayload('root'),
                target({type: 'workspace', workspace: ws('ws1')})
            )
        ).toEqual({ok: false, reason: 'already'});
        expect(
            verdict(
                collectionPayload('child'),
                target({type: 'workspace', workspace: ws('ws2')})
            )
        ).toEqual({ok: false, reason: 'workspace'});
    });

    it('needs edit on the source and create on the target', () => {
        const locked = col('locked', 'ws1', {
            parentId: 'root',
            capabilities: {
                createAsset: true,
                createCollection: true,
                edit: false,
            } as any,
        });
        expect(
            verdict(
                {type: 'collection', collection: locked},
                target({type: 'collection', collection: collections.other})
            )
        ).toEqual({ok: false, reason: 'capability'});
    });

    it('never goes into a basket or a story', () => {
        expect(
            verdict(
                collectionPayload('child'),
                target({type: 'basket', basket: basket('b')})
            )
        ).toEqual({ok: false, reason: 'type'});
    });
});

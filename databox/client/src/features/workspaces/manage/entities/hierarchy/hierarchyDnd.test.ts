import {describe, expect, it} from 'vitest';
import type {AttributeEntity} from '@/types/api';
import {resolveDrop, type TargetData} from './hierarchyDnd';

function entity(id: string, parentId: string | null): AttributeEntity {
    return {id, value: id, parentId} as AttributeEntity;
}

function node(
    e: AttributeEntity,
    index: number,
    ancestorIds: string[]
): TargetData {
    return {kind: 'node', entity: e, index, ancestorIds, isRoot: false};
}

// world > europe > [france, italy, spain]
const france = entity('france', 'europe');
const italy = entity('italy', 'europe');
const spain = entity('spain', 'europe');
const ancestors = ['world', 'europe'];

describe('resolveDrop', () => {
    it('moves down among the siblings, counted without the moved entity', () => {
        expect(
            resolveDrop(
                {entity: france, index: 0},
                node(spain, 2, ancestors),
                'after'
            )
        ).toEqual({
            entity: france,
            parentId: 'europe',
            position: 2,
            reparent: false,
        });
        expect(
            resolveDrop(
                {entity: france, index: 0},
                node(spain, 2, ancestors),
                'before'
            )
        ).toMatchObject({position: 1, reparent: false});
    });

    it('moves up among the siblings', () => {
        expect(
            resolveDrop(
                {entity: spain, index: 2},
                node(france, 0, ancestors),
                'before'
            )
        ).toMatchObject({parentId: 'europe', position: 0, reparent: false});
    });

    it('ignores a drop leaving the entity in place', () => {
        expect(
            resolveDrop(
                {entity: france, index: 0},
                node(italy, 1, ancestors),
                'before'
            )
        ).toBeUndefined();
        expect(
            resolveDrop(
                {entity: italy, index: 1},
                node(france, 0, ancestors),
                'after'
            )
        ).toBeUndefined();
    });

    it('moves inside another node, at the end of its children', () => {
        expect(
            resolveDrop(
                {entity: france, index: 0},
                node(italy, 1, ancestors),
                'inside'
            )
        ).toEqual({
            entity: france,
            parentId: 'italy',
            position: Number.MAX_SAFE_INTEGER,
            reparent: true,
        });
    });

    it('ignores a drop inside the current parent', () => {
        expect(
            resolveDrop(
                {entity: france, index: 0},
                node(entity('europe', 'world'), 0, ['world']),
                'inside'
            )
        ).toBeUndefined();
    });

    it('refuses to put a branch under itself', () => {
        const paris = entity('paris', 'france');
        expect(
            resolveDrop(
                {entity: france, index: 0},
                node(paris, 0, [...ancestors, 'france']),
                'inside'
            )
        ).toBeUndefined();
        expect(
            resolveDrop(
                {entity: france, index: 0},
                node(france, 0, ancestors),
                'inside'
            )
        ).toBeUndefined();
    });

    it('places beside a node of another parent', () => {
        const paris = entity('paris', 'france');
        expect(
            resolveDrop(
                {entity: italy, index: 1},
                node(paris, 0, [...ancestors, 'france']),
                'after'
            )
        ).toMatchObject({parentId: 'france', position: 1, reparent: true});
    });

    it('places an unclassified entity in the tree', () => {
        expect(
            resolveDrop(
                {entity: entity('portugal', null)},
                node(spain, 2, ancestors),
                'after'
            )
        ).toMatchObject({parentId: 'europe', position: 3, reparent: true});
    });

    it('takes a branch out of the hierarchy, not an unclassified entity', () => {
        expect(
            resolveDrop(
                {entity: france, index: 0},
                {kind: 'unclassified'},
                'inside'
            )
        ).toEqual({
            entity: france,
            parentId: null,
            position: 0,
            reparent: true,
        });
        expect(
            resolveDrop(
                {entity: entity('portugal', null)},
                {kind: 'unclassified'},
                'inside'
            )
        ).toBeUndefined();
    });
});

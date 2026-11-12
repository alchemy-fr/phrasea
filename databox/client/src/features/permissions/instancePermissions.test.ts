import {describe, expect, it} from 'vitest';
import {Ace, UserType} from '@/types/api';
import {
    computeMetadata,
    getRemoveRowWrites,
    getToggleWrite,
    hasInstancePermission,
    hasOtherPermissions,
    instancePermissions,
    mergeTypeAces,
    USER_WILDCARD,
} from './instancePermissions';

const VIEW = 1;
const SHARE = 256;
const QUARANTINE = 3;
const CREATE_WORKSPACE = 5;
const SHARE_PROFILE = 6;
const SHARE_BASKET = 7;
const PUBLISH_SAVED_SEARCH = 8;

function ace(data: Partial<Ace>): Ace {
    return {
        id: Math.random().toString(),
        userType: UserType.Group,
        userId: 'g1',
        objectId: null,
        mask: 0,
        metadata: [],
        ...data,
    } as Ace;
}

const permission = (key: string) =>
    instancePermissions.find(p => p.key === key)!;

describe('mergeTypeAces', () => {
    it('merges the type ACEs per user or group, "all users" first', () => {
        const rows = mergeTypeAces([
            ace({objectType: 'profile', metadata: [SHARE_PROFILE]}),
            ace({objectType: 'basket', metadata: [SHARE_BASKET]}),
            ace({
                userType: UserType.User,
                userId: 'u1',
                objectType: 'workspace',
                metadata: [CREATE_WORKSPACE],
            }),
        ]);

        expect(rows.map(r => r.key)).toEqual(['user:', 'group:g1', 'user:u1']);
        expect(rows[0].aces).toEqual({});
        expect(Object.keys(rows[1].aces)).toEqual(['profile', 'basket']);
        expect(hasInstancePermission(rows[1], permission('shareProfile'))).toBe(
            true
        );
        expect(hasInstancePermission(rows[1], permission('shareBasket'))).toBe(
            true
        );
        expect(
            hasInstancePermission(rows[1], permission('createWorkspace'))
        ).toBe(false);
        expect(
            hasInstancePermission(rows[2], permission('createWorkspace'))
        ).toBe(true);
    });

    it('fills the "all users" row with the wildcard ACEs', () => {
        const rows = mergeTypeAces([
            ace({
                userType: UserType.User,
                userId: null,
                objectType: 'saved_search',
                metadata: [PUBLISH_SAVED_SEARCH],
            }),
        ]);

        expect(rows).toHaveLength(1);
        expect(
            hasInstancePermission(rows[0], permission('publishSavedSearch'))
        ).toBe(true);
    });

    it('ignores the ACEs of an object', () => {
        const rows = mergeTypeAces([
            ace({
                objectType: 'basket',
                objectId: 'b1',
                metadata: [SHARE_BASKET],
            }),
        ]);

        expect(rows.map(r => r.key)).toEqual(['user:']);
    });

    it('tells the other permissions apart from the capabilities', () => {
        const [, capabilityOnly, withMask, withMetadata] = mergeTypeAces([
            ace({userId: 'a', objectType: 'basket', metadata: [SHARE_BASKET]}),
            ace({
                userId: 'b',
                objectType: 'basket',
                mask: SHARE,
                metadata: [SHARE_BASKET],
            }),
            ace({
                userId: 'c',
                objectType: 'workspace',
                metadata: [CREATE_WORKSPACE, QUARANTINE],
            }),
        ]);

        expect(hasOtherPermissions(capabilityOnly)).toBe(false);
        expect(hasOtherPermissions(withMask)).toBe(true);
        expect(hasOtherPermissions(withMetadata)).toBe(true);
    });

    it('reads no capability from the mask', () => {
        const [, row] = mergeTypeAces([
            ace({objectType: 'basket', mask: SHARE}),
        ]);

        expect(hasInstancePermission(row, permission('shareBasket'))).toBe(
            false
        );
    });
});

describe('computeMetadata', () => {
    it('adds or removes one value, keeping the others', () => {
        expect(computeMetadata([QUARANTINE], SHARE_BASKET, true)).toEqual([
            QUARANTINE,
            SHARE_BASKET,
        ]);
        expect(
            computeMetadata([QUARANTINE, SHARE_BASKET], SHARE_BASKET, false)
        ).toEqual([QUARANTINE]);
        expect(computeMetadata([SHARE_BASKET], SHARE_BASKET, true)).toEqual([
            SHARE_BASKET,
        ]);
        expect(computeMetadata([], SHARE_BASKET, false)).toEqual([]);
    });
});

describe('getToggleWrite', () => {
    it('creates the type ACE', () => {
        const [row] = mergeTypeAces([]);

        expect(getToggleWrite(row, permission('shareProfile'), true)).toEqual({
            type: 'put',
            userType: UserType.User,
            userId: USER_WILDCARD,
            objectType: 'profile',
            mask: 0,
            metadata: [SHARE_PROFILE],
        });
    });

    it('keeps the mask and the other metadata', () => {
        const [, row] = mergeTypeAces([
            ace({objectType: 'basket', mask: VIEW, metadata: [QUARANTINE]}),
        ]);

        expect(getToggleWrite(row, permission('shareBasket'), true)).toEqual({
            type: 'put',
            userType: UserType.Group,
            userId: 'g1',
            objectType: 'basket',
            mask: VIEW,
            metadata: [QUARANTINE, SHARE_BASKET],
        });
    });

    it('deletes the ACE once nothing is left', () => {
        const [, row] = mergeTypeAces([
            ace({objectType: 'basket', metadata: [SHARE_BASKET]}),
        ]);

        expect(getToggleWrite(row, permission('shareBasket'), false)).toEqual({
            type: 'delete',
            userType: UserType.Group,
            userId: 'g1',
            objectType: 'basket',
        });
    });

    it('keeps an ACE with a mask only', () => {
        const [, row] = mergeTypeAces([
            ace({objectType: 'basket', mask: VIEW, metadata: [SHARE_BASKET]}),
        ]);

        expect(getToggleWrite(row, permission('shareBasket'), false)).toEqual(
            expect.objectContaining({type: 'put', mask: VIEW, metadata: []})
        );
    });

    it('writes nothing when unchanged', () => {
        const [empty, row] = mergeTypeAces([
            ace({objectType: 'basket', metadata: [SHARE_BASKET]}),
        ]);

        expect(getToggleWrite(row, permission('shareBasket'), true)).toBeNull();
        expect(
            getToggleWrite(empty, permission('shareBasket'), false)
        ).toBeNull();
    });
});

describe('getRemoveRowWrites', () => {
    it('only revokes the capabilities', () => {
        const [, row] = mergeTypeAces([
            ace({
                objectType: 'basket',
                mask: VIEW,
                metadata: [SHARE_BASKET, QUARANTINE],
            }),
            ace({objectType: 'profile', metadata: [SHARE_PROFILE]}),
        ]);

        expect(getRemoveRowWrites(row)).toEqual([
            {
                type: 'put',
                userType: UserType.Group,
                userId: 'g1',
                objectType: 'basket',
                mask: VIEW,
                metadata: [QUARANTINE],
            },
            {
                type: 'delete',
                userType: UserType.Group,
                userId: 'g1',
                objectType: 'profile',
            },
        ]);
    });
});

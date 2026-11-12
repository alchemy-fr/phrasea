import {describe, expect, it} from 'vitest';
import type {TFunction} from 'i18next';
import {entityListPermissions} from './permissionDefinitions';
import {AclPermission} from './permissionTypes';

const t = ((_key: string, defaultValue: string) =>
    defaultValue) as unknown as TFunction;

describe('entityListPermissions', () => {
    it('offers the list management and the values management only', () => {
        expect(
            entityListPermissions(t).map(d => [d.type, d.key, d.label])
        ).toEqual([
            ['mask', AclPermission.OPERATOR, 'Manage the list'],
            ['mask', AclPermission.EDIT, 'Manage values'],
        ]);
    });
});

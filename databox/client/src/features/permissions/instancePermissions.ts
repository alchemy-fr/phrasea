import type {TFunction} from 'i18next';
import type {Ace, Group, InstanceCapabilities, User} from '@/types/api';
import {UserType} from '@/types/api';
import {AclExtraPermission, PermissionObject} from './permissionTypes';

/** Stands for every user in an ACE (stored with a null user id) */
export const USER_WILDCARD = '__ALL_USERS__';

export type InstancePermissionKey = keyof InstanceCapabilities;

/**
 * A capability granted outside any workspace: a metadata of an ACE set on a
 * whole object type (no object id). Mirrors `App\Security\InstancePermission` (API).
 */
export type InstancePermission = {
    key: InstancePermissionKey;
    objectType: PermissionObject;
    metadata: AclExtraPermission;
};

export const instancePermissions: InstancePermission[] = [
    {
        key: 'createWorkspace',
        objectType: PermissionObject.Workspace,
        metadata: AclExtraPermission.CREATE_WORKSPACE,
    },
    {
        key: 'shareProfile',
        objectType: PermissionObject.Profile,
        metadata: AclExtraPermission.SHARE_PROFILE,
    },
    {
        key: 'shareBasket',
        objectType: PermissionObject.Basket,
        metadata: AclExtraPermission.SHARE_BASKET,
    },
    {
        key: 'publishSavedSearch',
        objectType: PermissionObject.SavedSearch,
        metadata: AclExtraPermission.PUBLISH_SAVED_SEARCH,
    },
];

export const instancePermissionObjectTypes: PermissionObject[] = [
    ...new Set(instancePermissions.map(p => p.objectType)),
];

export function getInstancePermissionLabels(
    t: TFunction
): Record<InstancePermissionKey, {label: string; description: string}> {
    return {
        createWorkspace: {
            label: t(
                'instance_permissions.create_workspace',
                'Create a workspace'
            ),
            description: t(
                'instance_permissions.create_workspace_help',
                'Create new workspaces, and become their owner.'
            ),
        },
        shareProfile: {
            label: t(
                'instance_permissions.share_profile',
                'Share own profiles'
            ),
            description: t(
                'instance_permissions.share_profile_help',
                'Make the display profiles they own public, and grant other users and groups access to them.'
            ),
        },
        shareBasket: {
            label: t('instance_permissions.share_basket', 'Share own baskets'),
            description: t(
                'instance_permissions.share_basket_help',
                'Grant other users and groups access to the baskets they own.'
            ),
        },
        publishSavedSearch: {
            label: t(
                'instance_permissions.publish_saved_search',
                'Publish own searches'
            ),
            description: t(
                'instance_permissions.publish_saved_search_help',
                'Make the saved searches they own private (by link) or public, and grant access to them.'
            ),
        },
    };
}

/** Metadata of the ACEs of the object type which stand for instance capabilities */
export function getCapabilityMetadata(objectType: string): number[] {
    return instancePermissions
        .filter(p => p.objectType === objectType)
        .map(p => p.metadata);
}

export type InstancePermissionRow = {
    key: string;
    userType: UserType;
    /** null for every user */
    userId: string | null;
    user?: User | null;
    group?: Group | null;
    /** The ACE of each object type, if any */
    aces: Partial<Record<string, Ace>>;
};

export function getRowKey(userType: UserType, userId: string | null): string {
    return `${userType}:${userId ?? ''}`;
}

/**
 * Merges the type ACEs of the instance permission object types into one row
 * per user / group. The "all users" row always comes first, even without ACE.
 */
export function mergeTypeAces(aces: Ace[]): InstancePermissionRow[] {
    const allUsersKey = getRowKey(UserType.User, null);
    const rows = new Map<string, InstancePermissionRow>([
        [
            allUsersKey,
            {key: allUsersKey, userType: UserType.User, userId: null, aces: {}},
        ],
    ]);

    for (const ace of aces) {
        if (!ace.objectType || ace.objectId) {
            continue;
        }
        const key = getRowKey(ace.userType, ace.userId);
        let row = rows.get(key);
        if (!row) {
            row = {
                key,
                userType: ace.userType,
                userId: ace.userId,
                aces: {},
            };
            rows.set(key, row);
        }
        row.user ??= ace.user;
        row.group ??= ace.group;
        row.aces[ace.objectType] = ace;
    }

    return [...rows.values()];
}

export function hasInstancePermission(
    row: InstancePermissionRow,
    permission: InstancePermission
): boolean {
    return (row.aces[permission.objectType]?.metadata ?? []).includes(
        permission.metadata
    );
}

/** Whether the ACEs of the row grant more than the instance capabilities */
export function hasOtherPermissions(row: InstancePermissionRow): boolean {
    return Object.values(row.aces).some(
        ace =>
            !!ace &&
            (ace.mask !== 0 ||
                (ace.metadata ?? []).some(
                    m => !getCapabilityMetadata(ace.objectType!).includes(m)
                ))
    );
}

export function computeMetadata(
    previous: number[],
    value: number,
    granted: boolean
): number[] {
    const others = previous.filter(m => m !== value);

    return granted ? [...others, value] : others;
}

function sameValues(a: number[], b: number[]): boolean {
    return a.length === b.length && a.every(v => b.includes(v));
}

export type AceWrite =
    | {
          type: 'put';
          userType: UserType;
          userId: string;
          objectType: string;
          mask: number;
          metadata: number[];
      }
    | {
          type: 'delete';
          userType: UserType;
          userId: string;
          objectType: string;
      }
    | null;

/**
 * The write applying `metadata` to the type ACE of the row: the mask of the
 * ACE is kept, it is removed once nothing is left.
 */
export function getAceWrite(
    row: Pick<InstancePermissionRow, 'userType' | 'userId' | 'aces'>,
    objectType: string,
    metadata: number[]
): AceWrite {
    const ace = row.aces[objectType];
    const mask = ace?.mask ?? 0;
    const userId = row.userId ?? USER_WILDCARD;

    if (mask === 0 && metadata.length === 0) {
        return ace
            ? {type: 'delete', userType: row.userType, userId, objectType}
            : null;
    }
    if (ace && sameValues(ace.metadata ?? [], metadata)) {
        return null;
    }

    return {
        type: 'put',
        userType: row.userType,
        userId,
        objectType,
        mask,
        metadata,
    };
}

/** The write granting or revoking one capability */
export function getToggleWrite(
    row: InstancePermissionRow,
    permission: InstancePermission,
    granted: boolean
): AceWrite {
    return getAceWrite(
        row,
        permission.objectType,
        computeMetadata(
            row.aces[permission.objectType]?.metadata ?? [],
            permission.metadata,
            granted
        )
    );
}

/** The writes revoking every capability of the row, the rest of the ACEs kept */
export function getRemoveRowWrites(row: InstancePermissionRow): AceWrite[] {
    return Object.entries(row.aces)
        .filter(([, ace]) => !!ace)
        .map(([objectType, ace]) =>
            getAceWrite(
                row,
                objectType,
                (ace!.metadata ?? []).filter(
                    m => !getCapabilityMetadata(objectType).includes(m)
                )
            )
        )
        .filter(w => w !== null);
}

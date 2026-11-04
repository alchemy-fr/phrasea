export enum AclPermission {
    VIEW = 'VIEW',
    CREATE = 'CREATE',
    EDIT = 'EDIT',
    DELETE = 'DELETE',
    UNDELETE = 'UNDELETE',
    OPERATOR = 'OPERATOR',
    MASTER = 'MASTER',
    OWNER = 'OWNER',
    SHARE = 'SHARE',
    CHILD_VIEW = 'CHILD_VIEW',
    CHILD_CREATE = 'CHILD_CREATE',
    CHILD_EDIT = 'CHILD_EDIT',
    CHILD_DELETE = 'CHILD_DELETE',
    CHILD_UNDELETE = 'CHILD_UNDELETE',
    CHILD_OPERATOR = 'CHILD_OPERATOR',
    CHILD_MASTER = 'CHILD_MASTER',
    CHILD_OWNER = 'CHILD_OWNER',
    CHILD_SHARE = 'CHILD_SHARE',
}

export const aclMasks: Record<AclPermission, number> = {
    [AclPermission.VIEW]: 1,
    [AclPermission.CREATE]: 2,
    [AclPermission.EDIT]: 4,
    [AclPermission.DELETE]: 8,
    [AclPermission.UNDELETE]: 16,
    [AclPermission.OPERATOR]: 32,
    [AclPermission.MASTER]: 64,
    [AclPermission.OWNER]: 128,
    [AclPermission.SHARE]: 256,
    [AclPermission.CHILD_VIEW]: 512,
    [AclPermission.CHILD_CREATE]: 1024,
    [AclPermission.CHILD_EDIT]: 2048,
    [AclPermission.CHILD_DELETE]: 4096,
    [AclPermission.CHILD_UNDELETE]: 8192,
    [AclPermission.CHILD_OPERATOR]: 16384,
    [AclPermission.CHILD_MASTER]: 32768,
    [AclPermission.CHILD_OWNER]: 65536,
    [AclPermission.CHILD_SHARE]: 131072,
};

/** Extra (non-mask) permissions stored in the ACE metadata */
export enum AclExtraPermission {
    EDIT_PERMISSIONS = 1,
    MANAGE_USERS = 2,
    QUARANTINE = 3,
    QUARANTINE_BY_PASS = 4,
}

export enum PermissionObject {
    Asset = 'asset',
    AttributePolicy = 'attribute_policy',
    Basket = 'basket',
    Collection = 'collection',
    Profile = 'profile',
    RenditionPolicy = 'rendition_policy',
    SavedSearch = 'saved_search',
    Workspace = 'workspace',
    WorkspaceIntegration = 'integration',
}

export type PermissionDefinition =
    | {type: 'mask'; key: AclPermission; label: string; description?: string}
    | {
          type: 'extra';
          key: AclExtraPermission;
          label: string;
          description?: string;
      };

export function hasMask(mask: number, permission: AclPermission): boolean {
    return (mask & aclMasks[permission]) === aclMasks[permission];
}

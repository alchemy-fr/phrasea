
export enum AclPermission {
    VIEW = 'VIEW',
    SHARE = 'SHARE',
    CREATE = 'CREATE',
    EDIT = 'EDIT',
    DELETE = 'DELETE',
    UNDELETE = 'UNDELETE',
    OPERATOR = 'OPERATOR',
    MASTER = 'MASTER',
    OWNER = 'OWNER',
    ALL = 'ALL',
}

export const aclPermissions: { [key: string]: number } = {
    [AclPermission.VIEW]: 1,
    [AclPermission.SHARE]: 256,
    [AclPermission.CREATE]: 2,
    [AclPermission.EDIT]: 4,
    [AclPermission.DELETE]: 8,
    [AclPermission.UNDELETE]: 16,
    [AclPermission.OPERATOR]: 32,
    [AclPermission.MASTER]: 64,
    [AclPermission.OWNER]: 128,
}

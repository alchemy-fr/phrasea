import {Ace, UserType} from '../../types';
import {ReactNode} from 'react';
import {Optional} from '../../utils/types.ts';

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
    CHILD_VIEW = 'CHILD_VIEW',
    CHILD_CREATE = 'CHILD_CREATE',
    CHILD_EDIT = 'CHILD_EDIT',
    CHILD_DELETE = 'CHILD_DELETE',
    CHILD_UNDELETE = 'CHILD_UNDELETE',
    CHILD_OPERATOR = 'CHILD_OPERATOR',
    CHILD_MASTER = 'CHILD_MASTER',
    CHILD_OWNER = 'CHILD_OWNER',
    CHILD_SHARE = 'CHILD_SHARE',
    ALL = 'ALL',
}

export enum AclExtraPermission {
    EDIT_PERMISSIONS = 1,
    EDIT_TAG = 2,
    MANAGE_USERS = 3,
}

export type AclPermissionButAll = Exclude<AclPermission, AclPermission.ALL>;
export const aclPermissions: Record<AclPermissionButAll, number> = {
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
export type OnMaskChange = (
    userType: UserType,
    userId: string | null,
    mask: number,
    extraPermissions?: AclExtraPermission[]
) => Promise<Ace>;

export type OnPermissionDelete = (
    userType: UserType,
    userId: string | null
) => Promise<void>;

export enum PermissionObject {
    Collection = 'collection',
    Asset = 'asset',
    Workspace = 'workspace',
    Basket = 'basket',
    SavedSearch = 'saved_search',
    AttributePolicy = 'attribute_policy',
    RenditionPolicy = 'rendition_policy',
    AttributeList = 'attribute_list',
    WorkspaceIntegration = 'integration',
}

export enum PermissionType {
    Mask = 0,
    Extra = 1,
}

export type PermissionDefinition = (
    | {
          type: PermissionType.Extra;
          value: AclExtraPermission;
          key: AclExtraPermission;
      }
    | {
          type: PermissionType.Mask;
          value: number;
          key: AclPermission;
      }
) & {
    label?: ReactNode;
    description?: ReactNode;
};

export type PermissionDefinitionOverride = Optional<
    PermissionDefinition,
    'label' | 'description' | 'value'
>;

export type FilterPermissions = (def: PermissionDefinition) => boolean;

export type PermissionHelpers = Partial<
    Record<
        AclPermission,
        {
            label?: string;
            description?: ReactNode;
        }
    >
>;

export type ExtraPermissionsDefinition = {
    key: AclExtraPermission;
    label: ReactNode;
    description?: ReactNode;
};

import {Ace, UserType} from '../../types';

export type OnMaskChange = (
    userType: UserType,
    userId: string | null,
    mask: number
) => Promise<Ace>;

export type OnPermissionDelete = (
    userType: UserType,
    userId: string | null
) => Promise<void>;

export type PermissionObjectType =
    | 'collection'
    | 'asset'
    | 'workspace'
    | 'attribute_class';

export type DisplayedPermissions = string[] | undefined;

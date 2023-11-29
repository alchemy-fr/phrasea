import {UserType} from '../../types';

export type Permission = {
    id: string;
    userType: UserType;
    userId: string | null;
    mask: number;
};

export type OnMaskChange = (
    userType: UserType,
    userId: string | null,
    mask: number
) => Promise<void>;

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

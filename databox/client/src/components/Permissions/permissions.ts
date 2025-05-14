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

export enum PermissionObject {
    Collection = 'collection',
    Asset = 'asset',
    Workspace = 'workspace',
    Basket = 'basket',
    AttributeClass = 'attribute_class',
    AttributeList = 'attribute_list',
}

export type DisplayedPermissions = string[] | undefined;

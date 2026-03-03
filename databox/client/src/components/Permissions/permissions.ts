import {Ace, UserType} from '../../types';
import {AclPermission} from '../Acl/acl.ts';

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
    SavedSearch = 'saved_search',
    AttributePolicy = 'attribute_policy',
    AttributeList = 'attribute_list',
}

export type DisplayedPermissions = AclPermission[] | undefined;

export type BasePermissionProps = {
    displayedPermissions?: DisplayedPermissions;
    displayChildPermissions?: boolean;
    permissionHelper?: PermissionHelpers;
};

export type PermissionHelpers = Partial<
    Record<
        AclPermission,
        {
            label?: string;
            description?: string;
        }
    >
>;

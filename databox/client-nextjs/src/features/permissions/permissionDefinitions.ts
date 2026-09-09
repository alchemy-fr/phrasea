import type {TFunction} from 'i18next';
import {
    AclExtraPermission,
    AclPermission,
    PermissionDefinition,
} from './permissionTypes';

const mask = (
    key: AclPermission,
    label: string,
    description?: string
): PermissionDefinition => ({type: 'mask', key, label, description});
const extra = (
    key: AclExtraPermission,
    label: string,
    description?: string
): PermissionDefinition => ({type: 'extra', key, label, description});

export function genericPermissions(t: TFunction): PermissionDefinition[] {
    return [
        mask(AclPermission.VIEW, t('acl.permission.view', 'View')),
        mask(AclPermission.EDIT, t('acl.permission.edit', 'Edit')),
        mask(AclPermission.DELETE, t('acl.permission.delete', 'Delete')),
        mask(AclPermission.OPERATOR, t('acl.permission.operator', 'Operator')),
        mask(AclPermission.OWNER, t('acl.permission.owner', 'Owner')),
        extra(
            AclExtraPermission.EDIT_PERMISSIONS,
            t('acl.permission.edit_permissions', 'Edit permissions')
        ),
    ];
}

export function assetPermissions(t: TFunction): PermissionDefinition[] {
    return [
        mask(
            AclPermission.VIEW,
            t('acl.asset.view', 'View'),
            t(
                'acl.asset.view_desc',
                'Allows viewing this asset, but not necessarily its collection.'
            )
        ),
        mask(
            AclPermission.EDIT,
            t('acl.asset.edit', 'Edit attributes'),
            t('acl.asset.edit_desc', 'Allows editing the asset attributes.')
        ),
        mask(
            AclPermission.OPERATOR,
            t('acl.asset.operator', 'Manage'),
            t(
                'acl.asset.operator_desc',
                'Management actions: rename, move, copy, replace the source file, manage renditions.'
            )
        ),
        extra(
            AclExtraPermission.EDIT_PERMISSIONS,
            t('acl.asset.edit_permissions', 'Edit permissions / privacy')
        ),
        mask(AclPermission.DELETE, t('acl.asset.delete', 'Delete')),
        mask(
            AclPermission.OWNER,
            t('acl.asset.owner', 'Owner'),
            t('acl.asset.owner_desc', 'Full control over this asset.')
        ),
    ];
}

function childPermissions(t: TFunction, scope: string): PermissionDefinition[] {
    return [
        mask(
            AclPermission.CHILD_SHARE,
            t('acl.child.share', 'Share assets'),
            t(
                'acl.child.share_desc',
                'Allows sharing assets in this {{scope}}.',
                {scope}
            )
        ),
        mask(AclPermission.CHILD_VIEW, t('acl.child.view', 'View assets')),
        mask(
            AclPermission.CHILD_CREATE,
            t('acl.child.create', 'Create assets')
        ),
        mask(
            AclPermission.CHILD_EDIT,
            t('acl.child.edit', 'Edit asset attributes')
        ),
        mask(
            AclPermission.CHILD_OPERATOR,
            t('acl.child.operator', 'Manage assets')
        ),
        mask(
            AclPermission.CHILD_DELETE,
            t('acl.child.delete', 'Delete assets')
        ),
        mask(
            AclPermission.CHILD_OWNER,
            t('acl.child.owner', 'Owner of assets')
        ),
        extra(
            AclExtraPermission.QUARANTINE,
            t('acl.child.quarantine', 'View quarantined')
        ),
        extra(
            AclExtraPermission.QUARANTINE_BY_PASS,
            t('acl.child.quarantine_bypass', 'Bypass quarantine')
        ),
    ];
}

export function workspacePermissions(t: TFunction): PermissionDefinition[] {
    return [
        mask(
            AclPermission.VIEW,
            t('acl.workspace.view', 'Access'),
            t(
                'acl.workspace.view_desc',
                'Minimum permission to access the workspace (or mark it public).'
            )
        ),
        mask(
            AclPermission.CREATE,
            t('acl.workspace.create', 'Create collections')
        ),
        mask(
            AclPermission.EDIT,
            t('acl.workspace.edit', 'Manage'),
            t(
                'acl.workspace.edit_desc',
                'Manage the workspace settings (title, locales, tags, entities, renditions, attributes).'
            )
        ),
        mask(AclPermission.DELETE, t('acl.workspace.delete', 'Delete')),
        mask(AclPermission.OWNER, t('acl.workspace.owner', 'Owner')),
        extra(
            AclExtraPermission.EDIT_PERMISSIONS,
            t('acl.workspace.edit_permissions', 'Edit permissions')
        ),
        ...childPermissions(t, t('acl.scope.workspace', 'workspace')),
    ];
}

export function collectionPermissions(t: TFunction): PermissionDefinition[] {
    return [
        mask(AclPermission.VIEW, t('acl.collection.view', 'View')),
        mask(
            AclPermission.CREATE,
            t('acl.collection.create', 'Create collections')
        ),
        mask(AclPermission.EDIT, t('acl.collection.edit', 'Manage collection')),
        extra(
            AclExtraPermission.EDIT_PERMISSIONS,
            t(
                'acl.collection.edit_permissions',
                'Manage permissions of owned content'
            )
        ),
        mask(AclPermission.DELETE, t('acl.collection.delete', 'Delete')),
        mask(AclPermission.OWNER, t('acl.collection.owner', 'Owner')),
        ...childPermissions(t, t('acl.scope.collection', 'collection')),
    ];
}

export function integrationPermissions(t: TFunction): PermissionDefinition[] {
    return [
        mask(AclPermission.VIEW, t('acl.permission.view', 'View')),
        mask(AclPermission.EDIT, t('acl.permission.edit', 'Edit')),
        mask(AclPermission.OPERATOR, t('acl.integration.use', 'Use')),
        mask(AclPermission.CREATE, t('acl.integration.interact', 'Interact')),
    ];
}

export function renditionPolicyPermissions(
    t: TFunction
): PermissionDefinition[] {
    return [
        mask(AclPermission.VIEW, t('acl.permission.view', 'View')),
        mask(AclPermission.EDIT, t('acl.permission.edit', 'Edit')),
    ];
}

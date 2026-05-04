import {Workspace} from '../../../types';
import AclForm from '../../Permissions/AclForm.tsx';
import {
    AclExtraPermission,
    AclPermission,
    PermissionDefinitionOverride,
    PermissionObject,
    PermissionType,
} from '../../Permissions/permissionsTypes.ts';
import {useTranslation} from 'react-i18next';
import {useMemo} from 'react';
import {AclFormProps} from '../../Permissions/aclTypes.ts';
import {getAclDescriptions} from './aclDescriptions.ts';

type Props = AclFormProps<Workspace>;

export default function WorkspaceAclForm({data, helper}: Props) {
    const {t} = useTranslation();

    const definitions: PermissionDefinitionOverride[] = useMemo(() => {
        const aclDescriptions = getAclDescriptions(t);

        return [
            {
                type: PermissionType.Mask,
                key: AclPermission.VIEW,
                label: t('acl.permission.workspace.view.label', 'Can Access'),
                description: (
                    <div>
                        {t(
                            'acl.permission.workspace.view.desc',
                            'Minimal permission a user must  have to access Workspace. Otherwise, you can mark this workspace as public.'
                        )}
                    </div>
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.CREATE,
                label: t(
                    'acl.permission.workspace.create.label',
                    'Create Collections'
                ),
                description: t(
                    'acl.permission.workspace.create.desc',
                    'Can create collections within this workspace, but cannot edit or delete collections created by others.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.EDIT,
                label: t('acl.permission.workspace.edit.label', 'Manage'),
                description: t(
                    'acl.permission.workspace.edit.desc',
                    'Can manage this workspace (change title, add locales, tags, entities, renditions, attributes) but cannot edit assets within the workspace.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.DELETE,
                label: t('acl.permission.workspace.delete.label', 'Delete'),
                description: t(
                    'acl.permission.workspace.delete.desc',
                    'Can delete this workspace (Not implemented in GUI).'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.OWNER,
                label: t('acl.permission.workspace.owner.label', 'Owner'),
                description: t(
                    'acl.permission.workspace.owner.desc',
                    'Full control over the workspace, its collections and assets.'
                ),
            },
            {
                type: PermissionType.Extra,
                key: AclExtraPermission.EDIT_PERMISSIONS,
                value: AclExtraPermission.EDIT_PERMISSIONS,
                label: t(
                    'acl.permission.workspace.edit_permissions.label',
                    'Edit Permissions'
                ),
                description: t(
                    'acl.permission.workspace.edit_permissions.desc',
                    'Can edit permissions/privacy of collections and assets owned by user.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_SHARE,
                label: t(
                    'acl.permission.workspace.share_assets.label',
                    'Share Assets'
                ),
                description: t(
                    'acl.permission.workspace.share_assets.desc',
                    'Can share assets at the root level of this workspace.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_VIEW,
                label: t(
                    'acl.permission.workspace.view_assets.label',
                    'View Assets'
                ),
                description: t(
                    'acl.permission.workspace.view_assets.desc',
                    'Can view all assets in the workspace, but cannot see the collections they belong to.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_CREATE,
                label: t(
                    'acl.permission.workspace.create_assets.label',
                    'Create Assets'
                ),
                description: t(
                    'acl.permission.workspace.create_assets.desc',
                    'Can create assets at the root level of this workspace.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_EDIT,
                label: t(
                    'acl.permission.workspace.edit_assets.label',
                    'Edit Assets Attributes'
                ),
                description: t(
                    'acl.permission.workspace.edit_assets.desc',
                    'Can edit attributes of assets at the root level of this workspace.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_OPERATOR,
                label: t(
                    'acl.permission.workspace.assets_operator.label',
                    'Manage Assets'
                ),
                description: t(
                    'acl.permission.workspace.assets_operator.desc',
                    {
                        defaultValue:
                            'Can manage assets ({{manage_asset_desc}}) at the root level of this workspace.',
                        manage_asset_desc: aclDescriptions.aclOperatorDesc,
                    }
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_DELETE,
                label: t(
                    'acl.permission.workspace.delete_assets.label',
                    'Delete Assets'
                ),
                description: t(
                    'acl.permission.workspace.delete_assets.desc',
                    'Can delete assets at the root level of this workspace.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_OWNER,
                label: t(
                    'acl.permission.workspace.assets_owner.label',
                    'Owner of Assets'
                ),
                description: t(
                    'acl.permission.workspace.assets_owner.desc',
                    'Full control over all the assets of this workspace, except Permissions / Privacy.'
                ),
            },
        ];
    }, [t]);

    return (
        <AclForm
            helper={helper}
            objectId={data.id}
            objectType={PermissionObject.Workspace}
            filterDefinitions={def =>
                def.type !== PermissionType.Mask ||
                ![
                    AclPermission.SHARE,
                    AclPermission.OPERATOR,
                    AclPermission.UNDELETE,
                    AclPermission.CHILD_UNDELETE,
                    AclPermission.MASTER,
                    AclPermission.CHILD_MASTER,
                ].includes(def.key)
            }
            definitions={definitions}
        />
    );
}

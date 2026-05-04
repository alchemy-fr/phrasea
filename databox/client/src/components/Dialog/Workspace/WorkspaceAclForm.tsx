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
                label: t('acl.permission.workspace.view.label', 'Access'),
                description: t(
                    'acl.permission.workspace.view.desc',
                    'Minimum permission required to access this workspace. Alternatively, the workspace can be marked as public.'
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
                    'Allows creating collections within this workspace, but not editing or deleting collections created by others.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.EDIT,
                label: t('acl.permission.workspace.edit.label', 'Manage'),
                description: t(
                    'acl.permission.workspace.edit.desc',
                    'Allows managing this workspace (change title, add locales, tags, entities, renditions, attributes), but not editing assets within the workspace.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.DELETE,
                label: t('acl.permission.workspace.delete.label', 'Delete'),
                description: t(
                    'acl.permission.workspace.delete.desc',
                    'Allows deleting this workspace (not implemented in GUI).'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.OWNER,
                label: t('acl.permission.workspace.owner.label', 'Owner'),
                description: t(
                    'acl.permission.workspace.owner.desc',
                    'Full control over the workspace, its collections, and assets.'
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
                    'Allows editing permissions and privacy of collections and assets you own.'
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
                    'Allows sharing assets at the root level of this workspace.'
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
                    'Allows viewing all assets in the workspace, but not necessarily the collections to which they belong.'
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
                    'Allows creating assets at the root level of this workspace.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_EDIT,
                label: t(
                    'acl.permission.workspace.edit_assets.label',
                    'Edit Asset Attributes'
                ),
                description: t(
                    'acl.permission.workspace.edit_assets.desc',
                    'Allows editing attributes of assets at the root level of this workspace.'
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
                            'Allows management actions on assets ({{manage_asset_desc}}) at the root level of this workspace.',
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
                    'Allows deleting assets at the root level of this workspace.'
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
                    'Full control over all the assets of this workspace, except for permissions and privacy settings.'
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

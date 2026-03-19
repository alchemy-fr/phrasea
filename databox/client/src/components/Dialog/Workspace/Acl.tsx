import {Workspace} from '../../../types';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import AclForm from '../../Permissions/AclForm.tsx';
import ContentTab from '../Tabbed/ContentTab';
import {
    AclExtraPermission,
    AclPermission,
    PermissionDefinitionOverride,
    PermissionObject,
    PermissionType,
} from '../../Permissions/permissionsTypes.ts';
import {useTranslation} from 'react-i18next';
import {useMemo} from 'react';

type Props = DataTabProps<Workspace>;

export default function Acl({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const definitions: PermissionDefinitionOverride[] = useMemo(() => {
        return [
            {
                type: PermissionType.Mask,
                key: AclPermission.VIEW,
                label: t('acl.permission.workspace.view.label', 'Can Access'),
                description: (
                    <div>
                        <strong>
                            {t(
                                'acl.permission.workspace.view.desc',
                                'Every user must have at least this permission to access this workspace. Otherwise, you can mark this workspace as public.'
                            )}
                        </strong>
                        <br />
                        {t(
                            'acl.permission.workspace.view.desc',
                            'Can view this workspace and its assets, but cannot edit or delete them.'
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
                label: t('acl.permission.workspace.edit.label', 'Edit'),
                description: t(
                    'acl.permission.workspace.edit.desc',
                    'Can edit this workspace, but cannot edit assets within the workspace.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.DELETE,
                label: t('acl.permission.workspace.delete.label', 'Delete'),
                description: t(
                    'acl.permission.workspace.delete.desc',
                    'Can delete this workspace.'
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
                    'Can edit permissions for this workspace and its collections'
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
                key: AclPermission.CHILD_CREATE,
                label: t(
                    'acl.permission.workspace.create_assets.label',
                    'Create Assets'
                ),
                description: t(
                    'acl.permission.workspace.create_assets.desc',
                    'Can create assets at the root level of this workspace (not in any collection), but cannot edit or delete assets created by others.'
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
                    'Can edit attributes of assets at the root level of this workspace, such as title, tags, and other attributes, but cannot change permissions, source files, renditions, or share assets.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_OPERATOR,
                label: t(
                    'acl.permission.workspace.assets_operator.label',
                    'Edit Assets'
                ),
                description: t(
                    'acl.permission.workspace.assets_operator.desc',
                    'Can edit assets (attributes, source files, renditions) at the root level of this workspace, but cannot change permissions or share assets.'
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
                    'Can delete assets at the root level of this workspace, but cannot edit assets or change permissions.'
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
                    'Full control over the assets of this workspace'
                ),
            },
        ];
    }, [t]);

    return (
        <ContentTab
            onClose={onClose}
            minHeight={minHeight}
            disableGutters={true}
        >
            <AclForm
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
        </ContentTab>
    );
}

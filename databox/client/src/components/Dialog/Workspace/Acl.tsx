import {Workspace} from '../../../types';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import AclForm from '../../Acl/AclForm';
import ContentTab from '../Tabbed/ContentTab';
import {PermissionObject} from '../../Permissions/permissions';
import {AclPermission, aclPermissions} from '../../Acl/acl.ts';
import {useTranslation} from 'react-i18next';
import {useMemo} from 'react';

type Props = DataTabProps<Workspace>;

export default function Acl({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const permissionHelper = useMemo(() => {
        return {
            [AclPermission.VIEW]: {
                label: t('acl.permission.workspace.view.label', 'View'),
                description: (
                    <div>
                        <strong>
                            {t(
                                'acl.permission.workspace.view.desc_access',
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

            [AclPermission.CREATE]: {
                label: t(
                    'acl.permission.workspace.create.label',
                    'Create Collections'
                ),
                description: t(
                    'acl.permission.workspace.create.desc',
                    'Can create collections within this workspace, but cannot edit or delete collections created by others.'
                ),
            },
            [AclPermission.EDIT]: {
                label: t('acl.permission.workspace.edit.label', 'Edit'),
                description: t(
                    'acl.permission.workspace.edit.desc',
                    'Can edit this workspace, but cannot edit assets within the workspace.'
                ),
            },
            [AclPermission.DELETE]: {
                label: t('acl.permission.workspace.delete.label', 'Delete'),
                description: t(
                    'acl.permission.workspace.delete.desc',
                    'Can delete this workspace.'
                ),
            },
            [AclPermission.OWNER]: {
                label: t('acl.permission.workspace.owner.label', 'Owner'),
                description: t(
                    'acl.permission.workspace.owner.desc',
                    'Full control over this workspace. Does not include permissions on assets within the workspace, which are managed by separate permissions.'
                ),
            },
            [AclPermission.CHILD_SHARE]: {
                label: t(
                    'acl.permission.workspace.share_assets.label',
                    'Share Assets'
                ),
                description: t(
                    'acl.permission.workspace.share_assets.desc',
                    'Can share assets at the root level of this workspace.'
                ),
            },
            [AclPermission.CHILD_CREATE]: {
                label: t(
                    'acl.permission.workspace.create_assets.label',
                    'Create Assets'
                ),
                description: t(
                    'acl.permission.workspace.create_assets.desc',
                    'Can create assets at the root level of this workspace (not in any collection), but cannot edit or delete assets created by others.'
                ),
            },
            [AclPermission.CHILD_EDIT]: {
                label: t(
                    'acl.permission.workspace.edit_assets.label',
                    'Edit Assets Attributes'
                ),
                description: t(
                    'acl.permission.workspace.edit_assets.desc',
                    'Can edit attributes of assets at the root level of this workspace, such as title, tags, and other attributes, but cannot change permissions, source files, renditions, or share assets.'
                ),
            },
            [AclPermission.CHILD_OPERATOR]: {
                label: t(
                    'acl.permission.workspace.assets_operator.label',
                    'Edit Assets'
                ),
                description: t(
                    'acl.permission.workspace.assets_operator.desc',
                    'Can edit assets (attributes, source files, renditions) at the root level of this workspace, but cannot change permissions or share assets.'
                ),
            },
            [AclPermission.CHILD_DELETE]: {
                label: t(
                    'acl.permission.workspace.delete_assets.label',
                    'Delete Assets'
                ),
                description: t(
                    'acl.permission.workspace.delete_assets.desc',
                    'Can delete assets at the root level of this workspace, but cannot edit assets or change permissions.'
                ),
            },
            [AclPermission.CHILD_OWNER]: {
                label: t(
                    'acl.permission.workspace.assets_owner.label',
                    'Owner of Assets'
                ),
                description: t(
                    'acl.permission.workspace.assets_owner.desc',
                    'Full control over the workspace, its collections and assets.'
                ),
            },
        };
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
                displayedPermissions={
                    Object.keys(aclPermissions)
                        .filter(
                            p =>
                                ![
                                    AclPermission.SHARE,
                                    AclPermission.OPERATOR,
                                    AclPermission.UNDELETE,
                                    AclPermission.CHILD_UNDELETE,
                                    AclPermission.MASTER,
                                    AclPermission.CHILD_MASTER,
                                ].includes(p as AclPermission)
                        )
                        .concat([AclPermission.ALL]) as AclPermission[]
                }
                permissionHelper={permissionHelper}
            />
        </ContentTab>
    );
}

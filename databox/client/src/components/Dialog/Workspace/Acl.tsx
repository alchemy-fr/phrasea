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
            [AclPermission.CREATE]: {
                label: t(
                    'acl.permission.workspace.create',
                    'Create Collections'
                ),
                description: t(
                    'acl.permission.workspace.create_desc',
                    'Can create collections in this workspace, but cannot edit or delete collections created by others.'
                ),
            },
            [AclPermission.CHILD_CREATE]: {
                label: t(
                    'acl.permission.workspace.create_assets',
                    'Create Assets'
                ),
                description: t(
                    'acl.permission.workspace.create_assets_desc',
                    'Can create assets in this workspace at root level (not in any collection), but cannot edit or delete assets created by others.'
                ),
            },
            [AclPermission.CHILD_EDIT]: {
                label: t(
                    'acl.permission.workspace.edit_assets',
                    'Edit Assets Attributes'
                ),
                description: t(
                    'acl.permission.workspace.edit_assets_desc',
                    'Can edit attributes of assets in the workspace, such as title, tags, attributes but cannot change permissions, source file, renditions or share assets.'
                ),
            },
            [AclPermission.CHILD_DELETE]: {
                label: t(
                    'acl.permission.workspace.delete_assets',
                    'Delete Assets'
                ),
                description: t(
                    'acl.permission.workspace.delete_assets_desc',
                    'Can delete assets in the workspace, but cannot edit assets or change permissions.'
                ),
            },
            [AclPermission.CHILD_OPERATOR]: {
                label: t(
                    'acl.permission.workspace.assets_operator',
                    'Operator of Assets'
                ),
                description: t(
                    'acl.permission.workspace.assets_operator_desc',
                    'Can edit and delete assets in the workspace, but cannot change permissions.'
                ),
            },
            [AclPermission.CHILD_OWNER]: {
                label: t(
                    'acl.permission.workspace.assets_owner',
                    'Owner of Assets'
                ),
                description: t(
                    'acl.permission.workspace.assets_owner_desc',
                    'Full control of assets in the workspace'
                ),
            },
            [AclPermission.CHILD_SHARE]: {
                label: t(
                    'acl.permission.workspace.share_assets',
                    'Share Assets'
                ),
                description: t(
                    'acl.permission.workspace.share_assets_desc',
                    'Can share assets in the workspace'
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
                displayedPermissions={Object.keys(aclPermissions)
                    .filter(p => p !== AclPermission.SHARE)
                    .concat([AclPermission.ALL])}
                permissionHelper={permissionHelper}
            />
        </ContentTab>
    );
}

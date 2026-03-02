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
            },
            [AclPermission.CHILD_CREATE]: {
                label: t(
                    'acl.permission.workspace.create_assets',
                    'Create Assets'
                ),
            },
            [AclPermission.CHILD_EDIT]: {
                label: t('acl.permission.workspace.edit_assets', 'Edit Assets'),
            },
            [AclPermission.CHILD_DELETE]: {
                label: t(
                    'acl.permission.workspace.delete_assets',
                    'Delete Assets'
                ),
            },
            [AclPermission.CHILD_OPERATOR]: {
                label: t(
                    'acl.permission.workspace.assets_operator',
                    'Operator of Assets'
                ),
            },
            [AclPermission.CHILD_OWNER]: {
                label: t(
                    'acl.permission.workspace.assets_owner',
                    'Owner of Assets'
                ),
            },
            [AclPermission.CHILD_SHARE]: {
                label: t(
                    'acl.permission.workspace.share_assets',
                    'Share Assets'
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

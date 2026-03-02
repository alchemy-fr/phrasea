import {Collection} from '../../../types';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import AclForm from '../../Acl/AclForm';
import ContentTab from '../Tabbed/ContentTab';
import {PermissionObject} from '../../Permissions/permissions';
import {AclPermission} from '../../Acl/acl.ts';
import {useTranslation} from 'react-i18next';
import {useMemo} from 'react';

type Props = DataTabProps<Collection>;

export default function Acl({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const permissionHelper = useMemo(() => {
        return {
            [AclPermission.CREATE]: {
                label: t(
                    'acl.permission.collection.create',
                    'Create Sub Collections'
                ),
            },
            [AclPermission.CHILD_CREATE]: {
                label: t(
                    'acl.permission.collections.create_assets',
                    'Create Assets'
                ),
            },
            [AclPermission.CHILD_EDIT]: {
                label: t(
                    'acl.permission.collections.edit_assets',
                    'Edit Assets'
                ),
            },
            [AclPermission.CHILD_DELETE]: {
                label: t(
                    'acl.permission.collections.delete_assets',
                    'Delete Assets'
                ),
            },
            [AclPermission.CHILD_OPERATOR]: {
                label: t(
                    'acl.permission.collections.assets_operator',
                    'Operator of Assets'
                ),
            },
            [AclPermission.CHILD_OWNER]: {
                label: t(
                    'acl.permission.collections.assets_owner',
                    'Owner of Assets'
                ),
            },
            [AclPermission.CHILD_SHARE]: {
                label: t(
                    'acl.permission.collections.share_assets',
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
                displayChildPermissions={true}
                objectId={data.id}
                objectType={PermissionObject.Collection}
                permissionHelper={permissionHelper}
            />
        </ContentTab>
    );
}

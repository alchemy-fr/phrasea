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
                    'acl.permission.collection.create.label',
                    'Create Collections'
                ),
                description: t(
                    'acl.permission.collection.create_desc',
                    'Can create collections in this collection, but cannot edit or delete collections created by others. New collections will be shared with the same permissions as this collection.'
                ),
            },
            [AclPermission.CHILD_CREATE]: {
                label: t(
                    'acl.permission.collections.create_assets.label',
                    'Create Assets'
                ),
                description: t(
                    'acl.permission.collections.create_assets_desc',
                    'Can create assets, but cannot edit or delete assets created by others.'
                ),
            },
            [AclPermission.CHILD_EDIT]: {
                label: t(
                    'acl.permission.collections.edit_assets.label',
                    'Edit Assets Attributes'
                ),
                description: t(
                    'acl.permission.collections.edit_assets_desc',
                    'Can edit attributes of assets in the collection, such as title, tags, attributes but cannot change permissions, source file, renditions or share assets.'
                ),
            },
            [AclPermission.CHILD_DELETE]: {
                label: t(
                    'acl.permission.collections.delete_assets.label',
                    'Delete Assets'
                ),
                description: t(
                    'acl.permission.collections.delete_assets_desc',
                    'Can delete assets in the collection, but cannot edit assets or change permissions.'
                ),
            },
            [AclPermission.CHILD_OPERATOR]: {
                label: t(
                    'acl.permission.collections.assets_operator.label',
                    'Edit Assets'
                ),
                description: t(
                    'acl.permission.collections.assets_operator_desc',
                    'Can edit assets (attributes, source file, renditions), but cannot change permissions or share assets.'
                ),
            },
            [AclPermission.CHILD_OWNER]: {
                label: t(
                    'acl.permission.collections.assets_owner.label',
                    'Owner of Assets'
                ),
                description: t(
                    'acl.permission.collections.assets_owner_desc',
                    'Full control over assets in the collection, including editing attributes, source file, renditions, sharing and permissions.'
                ),
            },
            [AclPermission.CHILD_SHARE]: {
                label: t(
                    'acl.permission.collections.share_assets.label',
                    'Share Assets'
                ),
                description: t(
                    'acl.permission.collections.share_assets_desc',
                    'Can share assets in the collection'
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

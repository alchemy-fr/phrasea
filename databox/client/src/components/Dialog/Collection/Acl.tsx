import {Collection} from '../../../types';
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

type Props = DataTabProps<Collection>;

export default function Acl({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const definitions: PermissionDefinitionOverride[] = useMemo(() => {
        return [
            {
                type: PermissionType.Mask,
                key: AclPermission.VIEW,
                label: t('acl.permission.collection.view.label', 'View'),
                description: t(
                    'acl.permission.collection.view.desc',
                    'Can view this collection, its sub collections and theirs assets, but cannot edit or delete them.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CREATE,
                label: t(
                    'acl.permission.collection.create.label',
                    'Create Collections'
                ),
                description: t(
                    'acl.permission.collection.create.desc',
                    'Can create collections within this collection, but cannot edit or delete collections created by others. New collections will inherit the same permissions as this collection.'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.EDIT,
                label: t('acl.permission.collection.edit.label', 'Edit'),
                description: t(
                    'acl.permission.collection.edit.desc',
                    'Can edit this collection, but cannot edit assets within the collection.'
                ),
            },

            {
                type: PermissionType.Extra,
                key: AclExtraPermission.EDIT_PERMISSIONS,
                value: AclExtraPermission.EDIT_PERMISSIONS,
                label: t(
                    'acl.permission.collection.edit_permissions.label',
                    'Edit Permissions/Privacy'
                ),
                description: t(
                    'acl.permission.collection.edit_permissions.desc',
                    'Can edit privacy settings and permissions of this collection, such as making it public or private.'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.DELETE,
                label: t('acl.permission.collection.delete.label', 'Delete'),
                description: t(
                    'acl.permission.collection.delete.desc',
                    'Can delete this collection and its assets.'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.OWNER,
                label: t('acl.permission.collection.owner.label', 'Owner'),
                description: t(
                    'acl.permission.collection.owner.desc',
                    'Full control over this collection, its descendant collections and assets'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_SHARE,
                label: t(
                    'acl.permission.collection.share_assets.label',
                    'Share Assets'
                ),
                description: t(
                    'acl.permission.collection.share_assets.desc',
                    'Can share assets within the collection.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_VIEW,
                label: t(
                    'acl.permission.collection.view_assets.label',
                    'View Assets'
                ),
                description: t(
                    'acl.permission.collection.view_assets.desc',
                    'Can view assets of this collection and its children, but cannot view the collection itself.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_CREATE,
                label: t(
                    'acl.permission.collection.create_assets.label',
                    'Create Assets'
                ),
                description: t(
                    'acl.permission.collection.create_assets.desc',
                    'Can create assets, but cannot edit or delete assets created by others.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_EDIT,
                label: t(
                    'acl.permission.collection.edit_assets.label',
                    'Edit Assets Attributes'
                ),
                description: t(
                    'acl.permission.collection.edit_assets.desc',
                    'Can edit attributes of assets in the collection, such as title, tags, and other attributes, but cannot change permissions, source files, renditions, or share assets.'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_OPERATOR,
                label: t(
                    'acl.permission.collection.assets_operator.label',
                    'Edit Assets'
                ),
                description: t(
                    'acl.permission.collection.assets_operator.desc',
                    'Can edit assets (attributes, source files, renditions), but cannot change permissions or share assets.'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_DELETE,
                label: t(
                    'acl.permission.collection.delete_assets.label',
                    'Delete Assets'
                ),
                description: t(
                    'acl.permission.collection.delete_assets.desc',
                    'Can delete assets within the collection, but cannot edit assets or change permissions.'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_OWNER,
                label: t(
                    'acl.permission.collection.assets_owner.label',
                    'Owner of Assets'
                ),
                description: t(
                    'acl.permission.collection.assets_owner.desc',
                    'Full control over assets in the collection, including editing attributes, source files, renditions, sharing, and permissions.'
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
                displayChildPermissions={true}
                objectId={data.id}
                objectType={PermissionObject.Collection}
                definitions={definitions}
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
            />
        </ContentTab>
    );
}

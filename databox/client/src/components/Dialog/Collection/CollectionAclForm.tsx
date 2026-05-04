import {Collection} from '../../../types';
import AclForm from '../../Permissions/AclForm.tsx';
import {
    AclExtraPermission,
    AclPermission,
    PermissionDefinitionOverride,
    PermissionObject,
    PermissionType,
} from '../../Permissions/permissionsTypes.ts';
import {Trans, useTranslation} from 'react-i18next';
import React, {useEffect, useMemo, useState} from 'react';
import ParentAcl from '../../Permissions/ParentAcl.tsx';
import WorkspaceAclForm from '../Workspace/WorkspaceAclForm.tsx';
import {getCollection} from '../../../api/collection.ts';
import {AclFormProps} from '../../Permissions/aclTypes.ts';

type Props = AclFormProps<Collection>;

export default function CollectionAclForm({
    data,
    workspaceInheritance,
    helper,
    parentDisplay,
}: Props) {
    const {t} = useTranslation();
    const [parentCollection, setParentCollection] = useState<
        Collection | undefined
    >();

    useEffect(() => {
        async function fetchParentCollection() {
            if (data.parentId) {
                getCollection(data.parentId).then(collection => {
                    setParentCollection(collection);
                });
            }
        }

        fetchParentCollection();
    }, [data.id]);

    const definitions: PermissionDefinitionOverride[] = useMemo(() => {
        return [
            {
                type: PermissionType.Mask,
                key: AclPermission.VIEW,
                label: t('acl.permission.collection.view.label', 'View'),
                description: t(
                    'acl.permission.collection.view.desc',
                    'Can view this collection, its sub collections and their assets'
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
                    'Can create child collections within this collection. New child will inherit same permissions as parent collection.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.EDIT,
                label: t(
                    'acl.permission.collection.edit.label',
                    'Manage Collection'
                ),
                description: t(
                    'acl.permission.collection.edit.desc',
                    'Can manage this collection, but cannot edit assets within the collection.'
                ),
            },

            {
                type: PermissionType.Extra,
                key: AclExtraPermission.EDIT_PERMISSIONS,
                value: AclExtraPermission.EDIT_PERMISSIONS,
                label: t(
                    'acl.permission.collection.edit_permissions.label',
                    'Manage permissions of owned content'
                ),
                description: t(
                    'acl.permission.collection.edit_permissions.desc',
                    'Can edit permissions/privacy of collections and assets owned by user.'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.DELETE,
                label: t('acl.permission.collection.delete.label', 'Delete'),
                description: t(
                    'acl.permission.collection.delete.desc',
                    'Can delete this collection, child collections and their assets.'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.OWNER,
                label: t('acl.permission.collection.owner.label', 'Owner'),
                description: t(
                    'acl.permission.collection.owner.desc',
                    'Full control over this collection, child collections, but cannot edit permissions or privacy settings.'
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
                    'Can share assets of the collection and child collections.'
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
                    'Can view assets of this collection and child collections but not the collections to which they belong.'
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
                    'Can create assets in the collection and child collection'
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
                    'Can edit assets attributes in the collection and child collections.'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_OPERATOR,
                label: t(
                    'acl.permission.collection.assets_operator.label',
                    'Manage Assets'
                ),
                description: t(
                    'acl.permission.collection.assets_operator.desc',
                    'Can manage assets (Title, Tags, move, replace source files, view asset versions, edit renditions) in the collection and child collections, but cannot change permissions and privacy or share assets.'
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
                    'Can delete assets within the collection and child collections.'
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
                    'Full control over assets in the collection, except Permissions / Privacy.'
                ),
            },
        ];
    }, [t]);

    return (
        <>
            <AclForm
                helper={helper}
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

            {parentCollection ? (
                <ParentAcl
                    title={
                        <Trans
                            i18nKey={'collection.acl.parent.collection'}
                            defaults={`Permissions on <strong>{{name}}</strong>`}
                            values={{
                                name: parentCollection.titleTranslated,
                            }}
                        />
                    }
                    name={data.titleTranslated}
                    parentDisplay={parentDisplay}
                >
                    <CollectionAclForm
                        data={parentCollection}
                        parentDisplay={true}
                    />
                </ParentAcl>
            ) : null}

            {workspaceInheritance ? (
                <ParentAcl
                    title={
                        <Trans
                            i18nKey={'collection.acl.parent.workspace'}
                            defaults={`Permissions on Workspace <strong>{{name}}</strong>`}
                            values={{
                                name: data.workspace.nameTranslated,
                            }}
                        />
                    }
                    name={data.workspace.nameTranslated}
                    parentDisplay={parentDisplay}
                >
                    <WorkspaceAclForm
                        data={data.workspace}
                        parentDisplay={true}
                    />
                </ParentAcl>
            ) : null}
        </>
    );
}

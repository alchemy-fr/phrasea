import {Asset} from '../../../types';
import AclForm from '../../Permissions/AclForm.tsx';
import {
    AclExtraPermission,
    AclPermission,
    aclPermissions,
    PermissionDefinitionOverride,
    PermissionObject,
    PermissionType,
} from '../../Permissions/permissionsTypes.ts';
import ParentAcl from '../../Permissions/ParentAcl.tsx';
import {Trans} from 'react-i18next';
import WorkspaceAclForm from '../Workspace/WorkspaceAclForm.tsx';
import React, {useMemo} from 'react';
import CollectionAclForm from '../Collection/CollectionAclForm.tsx';
import {AclFormProps} from '../../Permissions/aclTypes.ts';
import {useTranslation} from 'react-i18next';

type Props = AclFormProps<Asset>;

export default function AssetAclForm({
    data,
    workspaceInheritance,
    helper,
    parentDisplay,
}: Props) {
    const {t} = useTranslation();
    const definitions: PermissionDefinitionOverride[] = useMemo(() => {
        return [
            {
                type: PermissionType.Mask,
                key: AclPermission.VIEW,
                label: t('acl.permission.asset.view.label', 'View'),
                description: t(
                    'acl.permission.asset.view.desc',
                    'Can view this asset.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.EDIT,
                label: t('acl.permission.asset.edit.label', 'Edit Attributes'),
                description: t(
                    'acl.permission.asset.edit.desc',
                    'Can edit asset attributes'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.OPERATOR,
                label: t('acl.permission.asset.operator.label', 'Manage'),
                description: t(
                    'acl.permission.asset.operator.desc',
                    'Can manage asset (Title, Tags, move, replace source files, view asset versions, edit renditions).'
                ),
            },

            {
                type: PermissionType.Extra,
                key: AclExtraPermission.EDIT_PERMISSIONS,
                value: AclExtraPermission.EDIT_PERMISSIONS,
                label: t(
                    'acl.permission.asset.edit_permissions.label',
                    'Edit Permissions/Privacy'
                ),
                description: t(
                    'acl.permission.asset.edit_permissions.desc',
                    'Can edit permissions/privacy of assets owned by user.'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.DELETE,
                label: t('acl.permission.asset.delete.label', 'Delete'),
                description: t(
                    'acl.permission.asset.delete.desc',
                    'Can delete this asset.'
                ),
            },

            {
                type: PermissionType.Mask,
                key: AclPermission.OWNER,
                label: t('acl.permission.asset.owner.label', 'Owner'),
                description: t(
                    'acl.permission.asset.owner.desc',
                    'Full control over this asset.'
                ),
            },
        ];
    }, [t]);

    return (
        <>
            <AclForm
                helper={helper}
                objectId={data.id}
                objectType={PermissionObject.Asset}
                definitions={definitions}
                filterDefinitions={({value, key}) =>
                    value < aclPermissions[AclPermission.CHILD_CREATE] &&
                    ![
                        AclPermission.CREATE,
                        AclPermission.UNDELETE,
                        AclPermission.MASTER,
                    ].includes(key as AclPermission)
                }
            />
            {data.collections?.map(c =>
                c.storyAsset ? (
                    <ParentAcl
                        title={
                            <Trans
                                i18nKey={'collection.acl.parent.story'}
                                defaults={`Permissions on Story <strong>{{name}}</strong>`}
                                values={{
                                    name: c.storyAsset.resolvedTitle,
                                }}
                            />
                        }
                        name={c.storyAsset.resolvedTitle || 'Story'}
                    >
                        <AssetAclForm
                            data={c.storyAsset!}
                            parentDisplay={true}
                        />
                    </ParentAcl>
                ) : (
                    <ParentAcl
                        title={
                            <Trans
                                i18nKey={'collection.acl.parent.collection'}
                                defaults={`Permissions on <strong>{{name}}</strong>`}
                                values={{
                                    name: c.titleTranslated,
                                }}
                            />
                        }
                        name={c.titleTranslated}
                        parentDisplay={parentDisplay}
                    >
                        <CollectionAclForm data={c} parentDisplay={true} />
                    </ParentAcl>
                )
            )}

            {workspaceInheritance ? (
                <ParentAcl
                    name={data.workspace.nameTranslated}
                    title={
                        <Trans
                            i18nKey={'collection.acl.parent.workspace'}
                            defaults={`Permissions on Workspace <strong>{{name}}</strong>`}
                            values={{
                                name: data.workspace.nameTranslated,
                            }}
                        />
                    }
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

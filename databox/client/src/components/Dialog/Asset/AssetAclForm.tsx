import {Asset} from '../../../types';
import AclForm from '../../Permissions/AclForm.tsx';
import {
    AclPermission,
    aclPermissions,
    PermissionObject,
} from '../../Permissions/permissionsTypes.ts';
import ParentAcl from '../../Permissions/ParentAcl.tsx';
import {Trans} from 'react-i18next';
import WorkspaceAclForm from '../Workspace/WorkspaceAclForm.tsx';
import React from 'react';
import CollectionAclForm from '../Collection/CollectionAclForm.tsx';
import {AclFormProps} from '../../Permissions/aclTypes.ts';

type Props = AclFormProps<Asset>;

export default function AssetAclForm({
    data,
    workspaceInheritance,
    helper,
    parentDisplay,
}: Props) {
    return (
        <>
            <AclForm
                helper={helper}
                objectId={data.id}
                objectType={PermissionObject.Asset}
                filterDefinitions={({value, key}) =>
                    value < aclPermissions[AclPermission.CHILD_CREATE] &&
                    ![
                        AclPermission.OPERATOR,
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

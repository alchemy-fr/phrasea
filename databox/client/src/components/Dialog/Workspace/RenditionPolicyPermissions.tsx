import {
    AclPermission,
    PermissionObject,
    PermissionType,
} from '../../Permissions/permissionsTypes.ts';
import AclForm from '../../Permissions/AclForm.tsx';
import {useMemo} from 'react';
import {useTranslation} from 'react-i18next';

type Props = {
    policyId: string;
    workspaceId?: string;
    collectionId?: string;
    isPublic: boolean;
};

export default function RenditionPolicyPermissions({
    isPublic,
    policyId,
}: Props) {
    const {t} = useTranslation();

    const definitions = useMemo(() => {
        return [
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_VIEW,
                label: t(
                    'acl.permission.rendition_policy.child_view.label',
                    'View'
                ),
                description: t(
                    'acl.permission.rendition_policy.child_view.desc',
                    'View rendition of this policy.'
                ),
            },
            {
                type: PermissionType.Mask,
                key: AclPermission.CHILD_EDIT,
                label: t(
                    'acl.permission.rendition_policy.child_edit.label',
                    'Edit'
                ),
                description: t(
                    'acl.permission.rendition_policy.child_edit.desc',
                    'Edit rendition of this policy.'
                ),
            },
        ];
    }, [t]);

    return (
        <AclForm
            objectType={PermissionObject.RenditionPolicy}
            objectId={policyId}
            definitions={definitions}
            filterDefinitions={
                isPublic
                    ? d =>
                          d.type === PermissionType.Mask &&
                          d.key === AclPermission.CHILD_EDIT
                    : d =>
                          d.type === PermissionType.Mask &&
                          [
                              AclPermission.CHILD_VIEW,
                              AclPermission.CHILD_EDIT,
                          ].includes(d.key)
            }
        />
    );
}

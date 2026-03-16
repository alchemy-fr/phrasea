import {useTranslation} from 'react-i18next';
import {
    AclPermission,
    aclPermissions,
    PermissionDefinition,
    PermissionDefinitionOverride,
    PermissionType,
} from './permissionsTypes.ts';
import {useMemo} from 'react';

type Props = {
    definitions?: PermissionDefinitionOverride[];
};

export default function useAclPermissionDefinitions({
    definitions,
}: Props): PermissionDefinition[] {
    const {t} = useTranslation();

    return useMemo(() => {
        const output: PermissionDefinition[] = [
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.VIEW],
                key: AclPermission.VIEW,
                label: t('acl.permission.view', 'View'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.SHARE],
                key: AclPermission.SHARE,
                label: t('acl.permission.share', 'Share'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.CREATE],
                key: AclPermission.CREATE,
                label: t('acl.permission.create', 'Create'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.EDIT],
                key: AclPermission.EDIT,
                label: t('acl.permission.edit', 'Edit'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.DELETE],
                key: AclPermission.DELETE,
                label: t('acl.permission.delete', 'Delete'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.UNDELETE],
                key: AclPermission.UNDELETE,
                label: t('acl.permission.undelete', 'Undelete'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.OPERATOR],
                key: AclPermission.OPERATOR,
                label: t('acl.permission.operator', 'Operator'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.MASTER],
                key: AclPermission.MASTER,
                label: t('acl.permission.master', 'Master'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.OWNER],
                key: AclPermission.OWNER,
                label: t('acl.permission.owner', 'Owner'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.CHILD_VIEW],
                key: AclPermission.CHILD_VIEW,
                label: t('acl.permission.child_create', 'Child View'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.CHILD_CREATE],
                key: AclPermission.CHILD_CREATE,
                label: t('acl.permission.child_create', 'Child Create'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.CHILD_EDIT],
                key: AclPermission.CHILD_EDIT,
                label: t('acl.permission.child_edit', 'Child Edit'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.CHILD_DELETE],
                key: AclPermission.CHILD_DELETE,
                label: t('acl.permission.child_delete', 'Child Delete'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.CHILD_UNDELETE],
                key: AclPermission.CHILD_UNDELETE,
                label: t('acl.permission.child_undelete', 'Child Undelete'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.CHILD_OPERATOR],
                key: AclPermission.CHILD_OPERATOR,
                label: t('acl.permission.child_operator', 'Child Operator'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.CHILD_MASTER],
                key: AclPermission.CHILD_MASTER,
                label: t('acl.permission.child_master', 'Child Master'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.CHILD_OWNER],
                key: AclPermission.CHILD_OWNER,
                label: t('acl.permission.child_owner', 'Child Owner'),
            },
            {
                type: PermissionType.Mask,
                value: aclPermissions[AclPermission.CHILD_SHARE],
                key: AclPermission.CHILD_SHARE,
                label: t('acl.permission.child_share', 'Child Share'),
            },
        ];

        if (definitions) {
            for (const def of definitions) {
                const value = output.find(
                    d => d.type === def.type && d.key === def.key
                );
                if (value) {
                    if (def.label) {
                        value.label = def.label;
                    }
                    if (def.description) {
                        value.description = def.description;
                    }
                }
            }
        }

        return output;
    }, [t, definitions]);
}

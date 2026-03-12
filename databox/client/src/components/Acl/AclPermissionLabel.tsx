import {useTranslation} from 'react-i18next';
import {AclPermission} from './acl';
import {PermissionHelpers} from '../Permissions/permissions.ts';
import {useMemo} from 'react';

type Props = {
    permissionHelper?: PermissionHelpers;
};

export default function useAclPermissionLabels({
    permissionHelper,
}: Props): Record<AclPermission, string> {
    const {t} = useTranslation();

    return useMemo(() => {
        const labels: Record<AclPermission, string> = {
            [AclPermission.VIEW]: t('acl.permission.view', 'View'),
            [AclPermission.SHARE]: t('acl.permission.share', 'Share'),
            [AclPermission.CREATE]: t('acl.permission.create', 'Create'),
            [AclPermission.EDIT]: t('acl.permission.edit', 'Edit'),
            [AclPermission.DELETE]: t('acl.permission.delete', 'Delete'),
            [AclPermission.UNDELETE]: t('acl.permission.undelete', 'Undelete'),
            [AclPermission.OPERATOR]: t('acl.permission.operator', 'Operator'),
            [AclPermission.MASTER]: t('acl.permission.master', 'Master'),
            [AclPermission.OWNER]: t('acl.permission.owner', 'Owner'),
            [AclPermission.CHILD_CREATE]: t(
                'acl.permission.child_create',
                'Child Create'
            ),
            [AclPermission.CHILD_EDIT]: t(
                'acl.permission.child_edit',
                'Child Edit'
            ),
            [AclPermission.CHILD_DELETE]: t(
                'acl.permission.child_delete',
                'Child Delete'
            ),
            [AclPermission.CHILD_UNDELETE]: t(
                'acl.permission.child_undelete',
                'Child Undelete'
            ),
            [AclPermission.CHILD_OPERATOR]: t(
                'acl.permission.child_operator',
                'Child Operator'
            ),
            [AclPermission.CHILD_MASTER]: t(
                'acl.permission.child_master',
                'Child Master'
            ),
            [AclPermission.CHILD_OWNER]: t(
                'acl.permission.child_owner',
                'Child Owner'
            ),
            [AclPermission.CHILD_SHARE]: t(
                'acl.permission.child_share',
                'Child Share'
            ),
            [AclPermission.ALL]: t('acl.permission.all', 'All'),
        };

        if (permissionHelper) {
            for (const [key, value] of Object.entries(permissionHelper)) {
                if (value.label) {
                    labels[key as AclPermission] = value.label;
                }
            }
        }

        return labels;
    }, [t, permissionHelper]);
}

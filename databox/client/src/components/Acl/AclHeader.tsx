import {useTranslation} from 'react-i18next';
import {AclPermission} from './acl';

export default function AclHeader({aclName}: {aclName: string}) {
    const {t} = useTranslation();

    const aclPermissionLabels: {[key: string]: string} = {
        [AclPermission.VIEW]: t('acl.permission.view', 'VIEW'),
        [AclPermission.SHARE]: t('acl.permission.share', 'SHARE'),
        [AclPermission.CREATE]: t('acl.permission.create', 'CREATE'),
        [AclPermission.EDIT]: t('acl.permission.edit', 'EDIT'),
        [AclPermission.DELETE]: t('acl.permission.delete', 'DELETE'),
        [AclPermission.UNDELETE]: t('acl.permission.undelete', 'UNDELETE'),
        [AclPermission.OPERATOR]: t('acl.permission.operator', 'OPERATOR'),
        [AclPermission.MASTER]: t('acl.permission.master', 'MASTER'),
        [AclPermission.OWNER]: t('acl.permission.owner', 'OWNER'),
        [AclPermission.ALL]: t('acl.permission.all', 'ALL'),
    };

    return <span>{aclPermissionLabels[aclName]}</span>;
}

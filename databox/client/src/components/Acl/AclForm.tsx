import {useCallback} from 'react';
import PermissionList from '../Permissions/PermissionList';
import {deleteAce, getAces, putAce} from '../../api/acl';
import {OnPermissionDelete, PermissionObject} from '../Permissions/permissions';
import {UserType} from '../../types';

type Props = {
    objectType: PermissionObject;
    objectId: string;
    displayedPermissions?: string[] | undefined;
};

export default function AclForm({
    objectType,
    objectId,
    displayedPermissions,
}: Props) {
    const loadPermissions = useCallback(async () => {
        return getAces(objectType, objectId);
    }, [objectType, objectId]);

    const updatePermission = useCallback(
        async (userType: UserType, userId: string | null, mask: number) => {
            return await putAce(userType, userId, objectType, objectId, mask);
        },
        [objectType, objectId]
    );

    const deletePermission: OnPermissionDelete = useCallback(
        async (userType: UserType, userId: string | null) => {
            await deleteAce(userType, userId, objectType, objectId);
        },
        [objectType, objectId]
    );

    return (
        <PermissionList
            displayedPermissions={displayedPermissions}
            loadPermissions={loadPermissions}
            updatePermission={updatePermission}
            deletePermission={deletePermission}
        />
    );
}

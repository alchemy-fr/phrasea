import {useCallback} from 'react';
import PermissionList from './PermissionList.tsx';
import {deleteAce, getAces, putAce} from '../../api/acl.ts';
import {
    AclExtraPermission,
    FilterPermissions,
    OnPermissionDelete,
    PermissionDefinitionOverride,
    PermissionObject,
} from './permissionsTypes.ts';
import {UserType} from '../../types.ts';

type Props = {
    objectType: PermissionObject;
    objectId: string;
    definitions?: PermissionDefinitionOverride[];
    filterDefinitions?: FilterPermissions;
    displayChildPermissions?: boolean;
    helper?: boolean;
};

export default function AclForm({objectType, objectId, ...rest}: Props) {
    const loadPermissions = useCallback(async () => {
        return getAces(objectType, objectId);
    }, [objectType, objectId]);

    const updatePermission = useCallback(
        async (
            userType: UserType,
            userId: string | null,
            mask: number,
            metadata?: AclExtraPermission[]
        ) => {
            return await putAce(
                userType,
                userId,
                objectType,
                objectId,
                mask,
                metadata
            );
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
            {...rest}
            loadPermissions={loadPermissions}
            updatePermission={updatePermission}
            deletePermission={deletePermission}
        />
    );
}

import {useCallback} from 'react';
import PermissionList from '../Permissions/PermissionList';
import {deleteAce, getAces, putAce} from '../../api/acl';
import {
    OnPermissionDelete,
    PermissionObjectType,
} from '../Permissions/permissions';
import {Ace, UserType} from '../../types';
import {useCollectionStore} from "../../store/collectionStore.ts";

type Props = {
    objectType: PermissionObjectType;
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

    const onListChanged = objectType === 'collection' ? (permissions: Ace[]) => {
        useCollectionStore.getState().partialUpdateCollection(objectId, {
            shared: permissions.length > 0
        });
    } : undefined;

    return (
        <PermissionList
            onListChanged={onListChanged}
            displayedPermissions={displayedPermissions}
            loadPermissions={loadPermissions}
            updatePermission={updatePermission}
            deletePermission={deletePermission}
        />
    );
}

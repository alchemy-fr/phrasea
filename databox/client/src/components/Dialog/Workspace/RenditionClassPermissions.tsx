import React, {useCallback} from 'react';
import {CollectionOrWorkspace, UserType} from "../../../types";
import {OnPermissionDelete, Permission} from "../../Permissions/permissions";
import PermissionList from "../../Permissions/PermissionList";
import {deleteRenditionRule, getRenditionRules, postRenditionRule} from "../../../api/renditionRule";

type Props = {
    classId: string;
    workspaceId?: string;
    collectionId?: string;
};

export default function RenditionClassPermissions({
                                                      classId,
                                                      collectionId,
                                                      workspaceId,
                                                  }: Props) {
    const loadPermissions = useCallback(async (): Promise<Permission[]> => {
        const rules = await getRenditionRules(classId);

        return rules.map(r => ({
            id: r.id,
            userType: r.groupId ? UserType.Group : UserType.User,
            userId: r.userId || r.groupId,
            mask: 1,
        }));
    }, [classId]);

    const updatePermission = useCallback(async (userType: UserType, userId: string | null) => {
        postRenditionRule(
            classId,
            collectionId ? CollectionOrWorkspace.Collection : CollectionOrWorkspace.Workspace,
            (collectionId || workspaceId)!,
            userType,
            userId
        );
    }, [classId]);

    const deletePermission: OnPermissionDelete = useCallback(async (userType: UserType, userId: string | null) => {
        const rules = await getRenditionRules(classId, {
            userType: userType === UserType.Group ? 1 : 0,
            userId,
            objectType: 0,
            objectId: workspaceId,
        });

        await Promise.all(rules.map(r => deleteRenditionRule(r.id)));
    }, [classId]);

    return <PermissionList
        displayedPermissions={[]}
        loadPermissions={loadPermissions}
        updatePermission={updatePermission}
        deletePermission={deletePermission}
    />
}

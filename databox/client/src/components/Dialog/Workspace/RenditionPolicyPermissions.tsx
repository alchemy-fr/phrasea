import {useCallback} from 'react';
import {
    Ace,
    CollectionOrWorkspace,
    RenditionRule,
    UserType,
} from '../../../types';
import {OnPermissionDelete} from '../../Permissions/permissions';
import PermissionList from '../../Permissions/PermissionList';
import {
    deleteRenditionRule,
    getRenditionRules,
    postRenditionRule,
} from '../../../api/renditionRule';

type Props = {
    policyId: string;
    workspaceId?: string;
    collectionId?: string;
};

export default function RenditionPolicyPermissions({
    policyId,
    collectionId,
    workspaceId,
}: Props) {
    const mapRuleToAce = (r: RenditionRule): Ace => {
        return {
            id: r.id,
            userType: r.groupId ? UserType.Group : UserType.User,
            userId: r.userId || r.groupId,
            mask: 1,
            user: r.user,
            group: r.group,
        };
    };

    const loadPermissions = useCallback(async (): Promise<Ace[]> => {
        const rules = await getRenditionRules(policyId);

        return rules.map(mapRuleToAce);
    }, [policyId]);

    const updatePermission = useCallback(
        async (userType: UserType, userId: string | null) => {
            return mapRuleToAce(
                await postRenditionRule(
                    policyId,
                    collectionId
                        ? CollectionOrWorkspace.Collection
                        : CollectionOrWorkspace.Workspace,
                    (collectionId || workspaceId)!,
                    userType,
                    userId
                )
            );
        },
        [policyId]
    );

    const deletePermission: OnPermissionDelete = useCallback(
        async (userType: UserType, userId: string | null) => {
            const rules = await getRenditionRules(policyId, {
                userType: userType === UserType.Group ? 1 : 0,
                userId,
                objectType: 0,
                objectId: workspaceId,
            });

            await Promise.all(rules.map(r => deleteRenditionRule(r.id)));
        },
        [policyId]
    );

    return (
        <PermissionList
            displayedPermissions={[]}
            loadPermissions={loadPermissions}
            updatePermission={updatePermission}
            deletePermission={deletePermission}
        />
    );
}

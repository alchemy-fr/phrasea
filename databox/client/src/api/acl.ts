import {apiClient} from '../init.ts';
import {Ace} from '../types';

import {AclExtraPermission} from '../components/Permissions/permissionsTypes.ts';

export async function getAces(
    objectType: string,
    objectId: string
): Promise<Ace[]> {
    const res = await apiClient.get(`/permissions/aces`, {
        params: {
            objectType,
            objectId,
            userIdWildcard: true,
            objectIdWildcard: true,
        },
    });

    const aces = res.data.map(
        (
            ace: Ace & {
                objectType: string;
                objectId: string;
            }
        ) => ({
            ...ace,
            wildcard: !ace.objectId,
        })
    );
    aces.sort((a: Ace, b: Ace) => {
        const u = (b.userId ? 0 : 1) - (a.userId ? 0 : 1);
        if (u === 0) {
            return (b.wildcard ? 1 : 0) - (a.wildcard ? 1 : 0);
        }

        return u;
    });

    return aces;
}

export async function putAce(
    userType: string,
    userId: string | null,
    objectType: string,
    objectId: string | undefined,
    mask: number,
    metadata?: AclExtraPermission[]
): Promise<Ace> {
    return (
        await apiClient.put(`/permissions/ace`, {
            userType,
            userId,
            objectType,
            objectId,
            mask,
            metadata,
        })
    ).data;
}

export async function deleteAce(
    userType: string,
    userId: string | null,
    objectType: string,
    objectId: string | undefined
): Promise<void> {
    await apiClient.delete(`/permissions/ace`, {
        data: {
            userType,
            userId,
            objectType,
            objectId,
        },
    });
}

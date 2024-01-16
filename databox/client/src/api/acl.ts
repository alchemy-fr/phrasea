import apiClient from './api-client';
import {Ace} from '../types';

export async function getAces(
    objectType: string,
    objectId: string
): Promise<Ace[]> {
    const res = await apiClient.get(`/permissions/aces`, {
        params: {
            objectType,
            objectId,
        },
    });

    return res.data;
}

export async function putAce(
    userType: string,
    userId: string | null,
    objectType: string,
    objectId: string | undefined,
    mask: number
): Promise<Ace> {
    return (await apiClient.put(`/permissions/ace`, {
        userType,
        userId,
        objectType,
        objectId,
        mask,
    })).data;
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

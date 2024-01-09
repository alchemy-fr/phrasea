import {CollectionOrWorkspace, RenditionRule, UserType} from '../types';
import apiClient from './api-client';

export async function getRenditionRules(
    classId: string,
    params: {} = {}
): Promise<RenditionRule[]> {
    return (
        await apiClient.get(`/rendition-rules`, {
            params: {
                ...params,
                allowed: classId,
            },
        })
    ).data['hydra:member'];
}

export async function postRenditionRule(
    classId: string,
    objectType: CollectionOrWorkspace,
    objectId: string,
    userType: UserType,
    userId: string | null
): Promise<RenditionRule> {
    return (await apiClient.post(`/rendition-rules`, {
        workspaceId:
            objectType === CollectionOrWorkspace.Workspace
                ? objectId
                : undefined,
        collectionId:
            objectType === CollectionOrWorkspace.Collection
                ? objectId
                : undefined,
        userId: userType === UserType.User ? userId : undefined,
        groupId: userType === UserType.Group ? userId : undefined,
        allowed: [`/rendition-classes/${classId}`],
    })).data;
}

export async function deleteRenditionRule(id: string): Promise<void> {
    await apiClient.delete(`/rendition-rules/${id}`);
}

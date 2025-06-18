import {CollectionOrWorkspace, RenditionRule, UserType} from '../types';
import apiClient from './api-client';

export async function getRenditionRules(
    policyId: string,
    params: {} = {}
): Promise<RenditionRule[]> {
    return (
        await apiClient.get(`/rendition-rules`, {
            params: {
                ...params,
                allowed: policyId,
            },
        })
    ).data['hydra:member'];
}

export async function postRenditionRule(
    policyId: string,
    objectType: CollectionOrWorkspace,
    objectId: string,
    userType: UserType,
    userId: string | null
): Promise<RenditionRule> {
    return (
        await apiClient.post(`/rendition-rules`, {
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
            allowed: [`/rendition-policies/${policyId}`],
        })
    ).data;
}

export async function deleteRenditionRule(id: string): Promise<void> {
    await apiClient.delete(`/rendition-rules/${id}`);
}

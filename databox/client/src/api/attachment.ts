import {apiClient} from '../init.ts';
import {AssetAttachment} from '../types';

const entityType = 'attachments';

type AttachmentInput = {
    name?: string | undefined;
    assetId: string;
    attachmentId: string;
};

export async function putAttachment(
    id: string,
    data: Partial<AttachmentInput>
): Promise<AssetAttachment> {
    const res = await apiClient.put(`/${entityType}/${id}`, data);

    return res.data;
}

export async function postAttachment(
    data: AttachmentInput
): Promise<AssetAttachment> {
    const res = await apiClient.post(`/${entityType}`, data);

    return res.data;
}

export async function deleteAttachment(id: string): Promise<void> {
    await apiClient.delete(`/${entityType}/${id}`);
}

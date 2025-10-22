import apiClient from './api-client';
import {AttributeList} from '../types';
import type {MultipartUpload} from '@alchemy/api';

const entityType = 'attachments';

type AttachmentInput = {
    name?: string | undefined;
    assetId: string;
    sourceFileId?: string | undefined;
    multipart?: MultipartUpload;
};

export async function putAttachment(
    id: string,
    data: Partial<AttachmentInput>
): Promise<AttributeList> {
    const res = await apiClient.put(`/${entityType}/${id}`, data);

    return res.data;
}

export async function postAttachment(
    data: AttachmentInput
): Promise<AttributeList> {
    const res = await apiClient.post(`/${entityType}`, data);

    return res.data;
}

export async function deleteAttachment(id: string): Promise<void> {
    await apiClient.delete(`/${entityType}/${id}`);
}

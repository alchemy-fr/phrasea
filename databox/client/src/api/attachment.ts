import {apiClient} from '../init.ts';
import {AssetAttachment} from '../types';
import type {MultipartUpload} from '@alchemy/api';
import {SourceFileInput} from './file.ts';

const entityType = 'attachments';

type AttachmentInput = {
    name?: string | undefined;
    assetId: string;
    sourceFileId?: string | undefined;
    sourceFile?: SourceFileInput;
    multipart?: MultipartUpload;
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

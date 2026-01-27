import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {ThreadMessage} from '../types.ts';
import {apiClient} from '../init.ts';

export async function getThreadMessages(
    threadId: string,
    nextUrl?: string
): Promise<NormalizedCollectionResponse<ThreadMessage>> {
    const res = await apiClient.get(nextUrl || `/threads/${threadId}/messages`);

    return getHydraCollection(res.data);
}

export async function getMessage(id: string): Promise<ThreadMessage> {
    return (await apiClient.get(`/messages/${id}`)).data;
}

export async function postThreadMessage(data: {
    threadKey: string;
    threadId?: string;
    content: string;
    attachments?: ThreadMessage['attachments'];
}): Promise<ThreadMessage> {
    const res = await apiClient.post(`/messages`, data);

    return res.data;
}

export async function putThreadMessage(
    id: string,
    data: {
        content: string;
    }
): Promise<ThreadMessage> {
    const res = await apiClient.put(`/messages/${id}`, data);

    return res.data;
}

export async function deleteThreadMessage(id: string): Promise<void> {
    await apiClient.delete(`/messages/${id}`);
}

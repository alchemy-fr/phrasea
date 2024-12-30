import {ApiCollectionResponse, getHydraCollection} from "./hydra.ts";
import {ThreadMessage} from "../types.ts";
import apiClient from "./api-client.ts";


export async function getThreadMessages(
    threadId: string,
    nextUrl?: string,
): Promise<ApiCollectionResponse<ThreadMessage>> {
    const res = await apiClient.get(nextUrl || `/threads/${threadId}/messages`);

    return getHydraCollection(res.data);
}

export async function postThreadMessage(data: {
    threadKey: string;
    threadId?: string;
    content: string;
}): Promise<ThreadMessage> {
    const res = await apiClient.post(`/messages`, data);

    return res.data;
}

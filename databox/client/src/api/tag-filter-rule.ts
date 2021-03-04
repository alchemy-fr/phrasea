import apiClient from "./api-client";
import {Collection, TagFilterRule, Workspace} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";
import {FilterRuleProps} from "../components/Media/TagFilterRule/FilterRule";

type TagFilterRuleOptions = {
    collectionId?: string,
}

export async function getTagFilterRules(options: TagFilterRuleOptions): Promise<ApiCollectionResponse<TagFilterRule>> {
    const res = await apiClient.get('/tag-filter-rules', {
        params: {
            ...options,
        },
    });

    return getHydraCollection(res.data);
}

export async function saveTagFilterRule(data: {
    id?: string,
    userId?: string,
    groupId?: string,
    collectionId?: string,
    workspaceId?: string,
    include?: string[],
    exclude?: string[],
}): Promise<ApiCollectionResponse<TagFilterRule>> {
    let res;

    if (data.id) {
        const d = {...data};
        delete d.id;
        res = await apiClient.put(`/tag-filter-rules/${data.id}`, d);
    } else {
        res = await apiClient.post('/tag-filter-rules', data);
    }

    return res.data;
}

export async function deleteTagFilterRule(id: string): Promise<void> {
    await apiClient.delete(`/tag-filter-rules/${id}`);
}

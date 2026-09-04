import {apiClient} from '../init.ts';
import {AttributeFilterRule} from '../types';
import {NormalizedCollectionResponse, getHydraCollection} from '@alchemy/api';

type AttributeFilterRuleOptions = {
    workspaceId?: string;
};

export async function getAttributeFilterRules(
    options: AttributeFilterRuleOptions
): Promise<NormalizedCollectionResponse<AttributeFilterRule>> {
    const res = await apiClient.get('/attribute-filter-rules', {
        params: {
            ...options,
        },
    });

    return getHydraCollection(res.data);
}

export async function saveAttributeFilterRule(data: {
    id?: string;
    userIds?: string[];
    groupIds?: string[];
    workspaceId?: string;
    condition?: string;
}): Promise<AttributeFilterRule> {
    let res;

    if (data.id) {
        const d = {...data};
        delete d.id;
        res = await apiClient.put(`/attribute-filter-rules/${data.id}`, d);
    } else {
        res = await apiClient.post('/attribute-filter-rules', data);
    }

    return res.data;
}

export async function deleteAttributeFilterRule(id: string): Promise<void> {
    await apiClient.delete(`/attribute-filter-rules/${id}`);
}

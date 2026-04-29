import {apiClient} from '../init.ts';
import {AttributeEntity} from '../types';
import {NormalizedCollectionResponse, getHydraCollection} from '@alchemy/api';
import {SortWay} from './common.ts';
import {PaginationParams} from './types.ts';
import {getBestTranslatedValue} from '@alchemy/i18n/src/Locale/localeHelper.ts';

const attributeEntityNS = '/attribute-entities';

type AttributeEntityOptions = {
    value?: string;
    list?: string;
} & PaginationParams;

export function formatAttributeEntityLabel(
    {emoji, value, translations}: AttributeEntity,
    options: {
        noTranslate?: boolean;
    } = {}
): string {
    const tValue = !options.noTranslate
        ? getBestTranslatedValue(translations, value)
        : value;

    if (emoji) {
        return `${emoji} ${tValue}`;
    }

    return tValue;
}

export async function getAttributeEntities({
    nextUrl,
    ...options
}: AttributeEntityOptions): Promise<
    NormalizedCollectionResponse<AttributeEntity>
> {
    const res = await apiClient.get(nextUrl ?? attributeEntityNS, {
        params: {
            ...options,
            [`order[value]`]: SortWay.ASC,
        },
    });

    return getHydraCollection<AttributeEntity>(res.data);
}

export async function postAttributeEntity(
    listId: string,
    data: Partial<AttributeEntity>
): Promise<AttributeEntity> {
    const res = await apiClient.post(attributeEntityNS, {
        ...data,
        list: `/entity-lists/${listId}`,
    });

    return res.data;
}

export async function putAttributeEntity(
    id: string,
    data: Partial<AttributeEntity>
): Promise<AttributeEntity> {
    const res = await apiClient.put(`${attributeEntityNS}/${id}`, data);

    return res.data;
}

export async function deleteAttributeEntity(id: string): Promise<void> {
    await apiClient.delete(`${attributeEntityNS}/${id}`);
}

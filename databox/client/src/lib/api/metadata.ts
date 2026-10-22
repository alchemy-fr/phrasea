import {api} from './http';
import {toPage} from './hydra';
import {
    AssetDataTemplate,
    AssetTypeFilter,
    Attribute,
    AttributeDefinition,
    AttributeEntity,
    AttributeEntityStatus,
    AttributeOrigin,
    AttributePolicy,
    BuiltInAttribute,
    EntityList,
    EntityName,
    FieldType,
    HydraCollection,
    Locale,
    MetadataTag,
    Page,
    Tag,
} from '@/types/api';
import {toIris} from '@/lib/utils/iri';
import {multipartUpload} from './upload';

// Attribute definitions ---------------------------------------------------

export type AttributeDefinitionListOptions = {
    workspaceId?: string;
    ids?: string[];
    slugs?: string[];
    /** Contains match on the name, the slug or any translation of the name */
    query?: string;
    type?: string | null;
    searchable?: boolean;
    sortable?: boolean;
    enabled?: boolean;
    multiple?: boolean;
    entityListId?: string;
    target?: AssetTypeFilter;
    limit?: number;
    url?: string;
};

export async function getAttributeDefinitionsPage(
    {url, ...params}: AttributeDefinitionListOptions,
    signal?: AbortSignal
): Promise<Page<AttributeDefinition>> {
    return toPage(
        await api.get<HydraCollection<AttributeDefinition>>(
            url ?? `/${EntityName.AttributeDefinition}`,
            {params: url ? undefined : params, signal}
        )
    );
}

export function getAttributeDefinition(
    id: string
): Promise<AttributeDefinition> {
    return api.get<AttributeDefinition>(
        `/${EntityName.AttributeDefinition}/${id}`
    );
}

export async function getBuiltInAttributes(): Promise<BuiltInAttribute[]> {
    return toPage(
        await api.get<HydraCollection<BuiltInAttribute>>(
            `/${EntityName.BuiltInAttribute}`
        )
    ).items;
}

export function putAttributeDefinition(
    id: string,
    data: Partial<AttributeDefinition>
): Promise<AttributeDefinition> {
    const {workspace: _w, ...rest} = data;

    return api.patch<AttributeDefinition>(
        `/${EntityName.AttributeDefinition}/${id}`,
        toIris(rest)
    );
}

export function postAttributeDefinition(
    data: Partial<AttributeDefinition>
): Promise<AttributeDefinition> {
    return api.post<AttributeDefinition>(
        `/${EntityName.AttributeDefinition}`,
        toIris(data)
    );
}

export function deleteAttributeDefinition(id: string): Promise<void> {
    return api.delete(`/${EntityName.AttributeDefinition}/${id}`);
}

export function duplicateAttributeDefinition(
    id: string
): Promise<AttributeDefinition> {
    return api.post<AttributeDefinition>(
        `/${EntityName.AttributeDefinition}/${id}/duplicate`,
        {}
    );
}

export function sortAttributeDefinitions(ids: string[]): Promise<void> {
    return api.post(`/${EntityName.AttributeDefinition}/sort`, ids);
}

export async function getAttributeFieldTypes(): Promise<FieldType[]> {
    return toPage(await api.get<HydraCollection<FieldType>>('/field-types'))
        .items;
}

// Attribute policies -------------------------------------------------------

export async function getAttributePolicies(
    workspaceId: string
): Promise<Page<AttributePolicy>> {
    return toPage(
        await api.get<HydraCollection<AttributePolicy>>(
            `/${EntityName.AttributePolicy}`,
            {
                params: {workspaceId},
            }
        )
    );
}

export function putAttributePolicy(
    id: string,
    data: Partial<AttributePolicy>
): Promise<AttributePolicy> {
    const {workspace: _w, ...rest} = data;

    return api.patch<AttributePolicy>(
        `/${EntityName.AttributePolicy}/${id}`,
        rest
    );
}

export function postAttributePolicy(
    data: Partial<AttributePolicy>
): Promise<AttributePolicy> {
    return api.post<AttributePolicy>(
        `/${EntityName.AttributePolicy}`,
        toIris(data)
    );
}

export function deleteAttributePolicy(id: string): Promise<void> {
    return api.delete(`/${EntityName.AttributePolicy}/${id}`);
}

// Tags ---------------------------------------------------------------------

export async function getTags(
    options: {
        workspace?: string;
        query?: string;
        limit?: number;
        url?: string;
    } = {},
    signal?: AbortSignal
): Promise<Page<Tag>> {
    return toPage(
        await api.get<HydraCollection<Tag>>(
            options.url ?? `/${EntityName.Tag}`,
            {
                params: options.url
                    ? undefined
                    : {
                          workspace: options.workspace,
                          query: options.query,
                          limit: options.limit,
                      },
                signal,
            }
        )
    );
}

export function getTag(id: string): Promise<Tag> {
    return api.get<Tag>(`/${EntityName.Tag}/${id}`);
}

export function postTag(data: Partial<Tag>): Promise<Tag> {
    return api.post<Tag>(`/${EntityName.Tag}`, toIris(data));
}

export function putTag(id: string, data: Partial<Tag>): Promise<Tag> {
    return api.patch<Tag>(`/${EntityName.Tag}/${id}`, toIris(data));
}

export function deleteTag(id: string): Promise<void> {
    return api.delete(`/${EntityName.Tag}/${id}`);
}

// Entity lists & attribute entities ---------------------------------------

export async function getEntityLists(
    options: {
        workspaceId: string;
        /** Contains match on the name */
        query?: string;
        limit?: number;
        url?: string;
    },
    signal?: AbortSignal
): Promise<Page<EntityList>> {
    return toPage(
        await api.get<HydraCollection<EntityList>>(
            options.url ?? `/${EntityName.EntityList}`,
            {
                params: options.url
                    ? undefined
                    : {
                          'workspace': options.workspaceId,
                          'name': options.query,
                          'order[name]': 'asc',
                          'limit': options.limit,
                      },
                signal,
            }
        )
    );
}

export function getEntityList(id: string): Promise<EntityList> {
    return api.get<EntityList>(`/${EntityName.EntityList}/${id}`);
}

export function postEntityList(
    workspaceId: string,
    data: Partial<EntityList>
): Promise<EntityList> {
    return api.post<EntityList>(`/${EntityName.EntityList}`, {
        ...data,
        workspace: `/${EntityName.Workspace}/${workspaceId}`,
    });
}

export function putEntityList(
    id: string,
    data: Partial<EntityList>
): Promise<EntityList> {
    return api.patch<EntityList>(
        `/${EntityName.EntityList}/${id}`,
        toIris(data)
    );
}

export function deleteEntityList(id: string): Promise<void> {
    return api.delete(`/${EntityName.EntityList}/${id}`);
}

export async function exportEntityList(
    listId: string,
    options: {format: string; locale?: string}
): Promise<{blob: Blob; filename: string}> {
    const res = await api.raw(`/${EntityName.EntityList}/${listId}/export`, {
        method: 'post',
        json: options,
    });
    const disposition = res.headers.get('content-disposition');
    const filename =
        disposition?.match(/filename="?([^"]+)"?/)?.[1] ?? 'export';

    return {blob: await res.blob(), filename};
}

export function importEntityList(
    listId: string,
    format: string,
    data: string
): Promise<void> {
    return api.post(`/${EntityName.EntityList}/${listId}/import`, {
        format,
        data,
    });
}

export function clearEntityList(listId: string): Promise<void> {
    return api.post(`/${EntityName.EntityList}/${listId}/clear`, {});
}

export type AttributeEntitySortField =
    | 'value'
    | 'status'
    | 'note'
    | 'createdAt'
    | 'updatedAt';

export async function getAttributeEntities(options: {
    list?: string;
    workspace?: string;
    /** Search-as-you-type on Elasticsearch: ignores the ORM-only filters and sorts */
    query?: string;
    /** Contains match on the value (keeps the sorts and the other filters) */
    value?: string;
    /** Contains match on the note */
    note?: string;
    status?: AttributeEntityStatus | AttributeEntityStatus[];
    /** Also count the attribute values referencing each entity (`usage`) */
    usage?: boolean;
    limit?: number;
    /** Defaults to `{value: 'asc'}` */
    order?: Partial<Record<AttributeEntitySortField, 'asc' | 'desc'>>;
    url?: string;
}): Promise<Page<AttributeEntity>> {
    return toPage(
        await api.get<HydraCollection<AttributeEntity>>(
            options.url ?? `/${EntityName.AttributeEntity}`,
            {
                params: options.url
                    ? undefined
                    : {
                          list: options.list,
                          workspace: options.workspace,
                          query: options.query,
                          value: options.value,
                          note: options.note,
                          status: options.status,
                          usage: options.usage ? 1 : undefined,
                          limit: options.limit,
                          ...Object.fromEntries(
                              Object.entries(
                                  options.order ?? {value: 'asc'}
                              ).map(([k, v]) => [`order[${k}]`, v])
                          ),
                      },
            }
        )
    );
}

export function getAttributeEntity(id: string): Promise<AttributeEntity> {
    return api.get<AttributeEntity>(`/${EntityName.AttributeEntity}/${id}`);
}

export function postAttributeEntity(
    listId: string,
    data: Partial<AttributeEntity>
): Promise<AttributeEntity> {
    return api.post<AttributeEntity>(`/${EntityName.AttributeEntity}`, {
        ...data,
        list: `/${EntityName.EntityList}/${listId}`,
    });
}

export function putAttributeEntity(
    id: string,
    data: Partial<AttributeEntity>
): Promise<AttributeEntity> {
    return api.patch<AttributeEntity>(
        `/${EntityName.AttributeEntity}/${id}`,
        toIris(data)
    );
}

export function deleteAttributeEntity(id: string): Promise<void> {
    return api.delete(`/${EntityName.AttributeEntity}/${id}`);
}

export async function uploadAttributeEntityImage(
    id: string,
    file: File
): Promise<AttributeEntity> {
    const multipart = await multipartUpload(file);

    return api.post<AttributeEntity>(
        `/${EntityName.AttributeEntity}/${id}/image`,
        {multipart}
    );
}

export function deleteAttributeEntityImage(id: string): Promise<void> {
    return api.delete(`/${EntityName.AttributeEntity}/${id}/image`);
}

/**
 * The values of attribute definitions across the assets the user can read,
 * newest assets first. The pages hold assets: `limit` is a number of assets,
 * each page holds every matching value of its assets (possibly more than
 * `limit` items), and `total` counts the assets.
 */
export async function getAttributesOfDefinition(
    options: {
        definition: string | string[];
        value?: string | string[];
        origin?: AttributeOrigin;
        asset?: string;
        limit?: number;
        url?: string;
    },
    signal?: AbortSignal
): Promise<Page<Attribute>> {
    return toPage(
        await api.get<HydraCollection<Attribute>>(
            options.url ?? '/attributes',
            {
                params: options.url
                    ? undefined
                    : {
                          definition: options.definition,
                          value: options.value,
                          origin: options.origin,
                          asset: options.asset,
                          limit: options.limit,
                      },
                signal,
            }
        )
    );
}

export function mergeAttributeEntities(
    keptId: string,
    ids: string[]
): Promise<AttributeEntity> {
    return api.put<AttributeEntity>(
        `/${EntityName.AttributeEntity}/${keptId}/merge`,
        {
            ids: ids.filter(id => id !== keptId),
        }
    );
}

// Templates ----------------------------------------------------------------

export async function getAssetDataTemplates(
    options: {
        workspace: string;
        collection?: string;
        query?: string;
        limit?: number;
        url?: string;
    },
    signal?: AbortSignal
): Promise<Page<AssetDataTemplate>> {
    return toPage(
        await api.get<HydraCollection<AssetDataTemplate>>(
            options.url ?? `/${EntityName.AssetDataTemplate}`,
            {
                params: options.url
                    ? undefined
                    : {
                          workspace: options.workspace,
                          collection: options.collection,
                          query: options.query,
                          limit: options.limit,
                      },
                signal,
            }
        )
    );
}

export function getAssetDataTemplate(id: string): Promise<AssetDataTemplate> {
    return api.get<AssetDataTemplate>(`/${EntityName.AssetDataTemplate}/${id}`);
}

export function postAssetDataTemplate(
    data: Partial<AssetDataTemplate>
): Promise<AssetDataTemplate> {
    return api.post<AssetDataTemplate>(
        `/${EntityName.AssetDataTemplate}`,
        data
    );
}

export function putAssetDataTemplate(
    id: string,
    data: Partial<AssetDataTemplate>
): Promise<AssetDataTemplate> {
    return api.patch<AssetDataTemplate>(
        `/${EntityName.AssetDataTemplate}/${id}`,
        data
    );
}

// Locales ------------------------------------------------------------------

export async function getLocales(): Promise<Locale[]> {
    return toPage(await api.get<HydraCollection<Locale>>('/locales')).items;
}

// Metadata tags (exiftool dictionary) -------------------------------------

/**
 * Suggests the namespaces matching the query, or the tags of a namespace
 * once the query contains a colon ("IPTC:Key").
 */
export async function getMetadataTags(
    query: string,
    signal?: AbortSignal
): Promise<MetadataTag[]> {
    return toPage(
        await api.get<HydraCollection<MetadataTag>>('/metadata-tags', {
            params: {query},
            signal,
        })
    ).items;
}

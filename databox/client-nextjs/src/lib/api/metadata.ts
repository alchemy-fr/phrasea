import {api} from './http';
import {toPage} from './hydra';
import {
    AssetDataTemplate,
    AssetTypeFilter,
    AttributeDefinition,
    AttributeEntity,
    AttributePolicy,
    BuiltInAttribute,
    EntityList,
    EntityName,
    FieldType,
    HydraCollection,
    Locale,
    Page,
    Tag,
} from '@/types/api';
import {toIris} from '@/lib/utils/iri';

// Attribute definitions ---------------------------------------------------

export async function getAttributeDefinitions(): Promise<
    AttributeDefinition[]
> {
    return toPage(
        await api.get<HydraCollection<AttributeDefinition>>(
            `/${EntityName.AttributeDefinition}`,
            {
                params: {limit: 1000},
            }
        )
    ).items;
}

export async function getWorkspaceAttributeDefinitions(options: {
    workspaceId: string;
    target?: AssetTypeFilter;
    query?: string;
    type?: string | null;
    url?: string;
}): Promise<Page<AttributeDefinition>> {
    return toPage(
        await api.get<HydraCollection<AttributeDefinition>>(
            options.url ?? `/${EntityName.AttributeDefinition}`,
            {
                params: options.url
                    ? undefined
                    : {
                          workspaceId: options.workspaceId,
                          target: options.target ?? AssetTypeFilter.All,
                          name: options.query,
                          type: options.type,
                          limit: 100,
                      },
            }
        )
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

    return api.put<AttributeDefinition>(
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

    return api.put<AttributePolicy>(
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
    options: {workspace?: string; query?: string; url?: string} = {}
): Promise<Page<Tag>> {
    return toPage(
        await api.get<HydraCollection<Tag>>(
            options.url ?? `/${EntityName.Tag}`,
            {
                params: options.url
                    ? undefined
                    : {workspace: options.workspace, query: options.query},
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
    return api.put<Tag>(`/${EntityName.Tag}/${id}`, toIris(data));
}

export function deleteTag(id: string): Promise<void> {
    return api.delete(`/${EntityName.Tag}/${id}`);
}

// Entity lists & attribute entities ---------------------------------------

export async function getEntityLists(options: {
    workspaceId: string;
    query?: string;
    url?: string;
}): Promise<Page<EntityList>> {
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
                      },
            }
        )
    );
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
    return api.put<EntityList>(`/${EntityName.EntityList}/${id}`, toIris(data));
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

export async function getAttributeEntities(options: {
    list?: string;
    query?: string;
    url?: string;
}): Promise<Page<AttributeEntity>> {
    return toPage(
        await api.get<HydraCollection<AttributeEntity>>(
            options.url ?? `/${EntityName.AttributeEntity}`,
            {
                params: options.url
                    ? undefined
                    : {
                          'list': options.list,
                          'query': options.query,
                          'order[value]': 'asc',
                      },
            }
        )
    );
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
    return api.put<AttributeEntity>(
        `/${EntityName.AttributeEntity}/${id}`,
        toIris(data)
    );
}

export function deleteAttributeEntity(id: string): Promise<void> {
    return api.delete(`/${EntityName.AttributeEntity}/${id}`);
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

export async function getAssetDataTemplates(options: {
    workspace: string;
    collection?: string;
    url?: string;
}): Promise<Page<AssetDataTemplate>> {
    return toPage(
        await api.get<HydraCollection<AssetDataTemplate>>(
            options.url ?? `/${EntityName.AssetDataTemplate}`,
            {
                params: options.url
                    ? undefined
                    : {
                          workspace: options.workspace,
                          collection: options.collection,
                      },
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
    return api.put<AssetDataTemplate>(
        `/${EntityName.AssetDataTemplate}/${id}`,
        data
    );
}

// Locales ------------------------------------------------------------------

export async function getLocales(): Promise<Locale[]> {
    return toPage(await api.get<HydraCollection<Locale>>('/locales')).items;
}

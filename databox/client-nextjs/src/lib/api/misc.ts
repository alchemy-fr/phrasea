import {api} from './http';
import {toPage} from './hydra';
import {
    Ace,
    ApiFile,
    AssetAttachment,
    AssetExport,
    AssetRendition,
    AssetType,
    Basket,
    BasketAsset,
    CmsPage,
    DisplayProfile,
    DuplicateAsset,
    EntityName,
    Group,
    HydraCollection,
    OperationTask,
    Page,
    ProfileItem,
    RenditionDefinition,
    RenditionPolicy,
    SavedSearch,
    Share,
    ThreadMessage,
    User,
    UserType,
    Workflow,
} from '@/types/api';
import type {MultipartUpload} from './upload';
import type {SourceFileInput} from './assets';
import {toIris} from '@/lib/utils/iri';

// Baskets -------------------------------------------------------------------

export type BasketListOptions = {
    query?: string;
    includeArchived?: boolean;
    url?: string;
};

export async function getBaskets({
    url,
    ...params
}: BasketListOptions = {}): Promise<Page<Basket>> {
    return toPage(
        await api.get<HydraCollection<Basket>>(url ?? `/${EntityName.Basket}`, {
            params: url ? undefined : params,
        })
    );
}

export function getBasket(id: string): Promise<Basket> {
    return api.get<Basket>(`/${EntityName.Basket}/${id}`);
}

export async function getBasketAssets(
    id: string,
    url?: string
): Promise<Page<BasketAsset>> {
    return toPage(
        await api.get<HydraCollection<BasketAsset>>(
            url ?? `/${EntityName.Basket}/${id}/assets`
        )
    );
}

export function postBasket(data: Partial<Basket>): Promise<Basket> {
    return api.post<Basket>(`/${EntityName.Basket}`, data);
}

export function putBasket(id: string, data: Partial<Basket>): Promise<Basket> {
    return api.put<Basket>(`/${EntityName.Basket}/${id}`, toIris(data));
}

export function deleteBasket(id: string): Promise<void> {
    return api.delete(`/${EntityName.Basket}/${id}`);
}

export function archiveBasket(id: string): Promise<Basket> {
    return api.post<Basket>(`/${EntityName.Basket}/${id}/archive`, {});
}

export function unarchiveBasket(id: string): Promise<Basket> {
    return api.post<Basket>(`/${EntityName.Basket}/${id}/unarchive`, {});
}

export function addToBasket(
    basketId: string | undefined,
    assetIds: string[]
): Promise<Basket> {
    return api.post<Basket>(
        `/${EntityName.Basket}/${basketId ?? 'default'}/assets`,
        {
            assets: assetIds.map(id => ({id})),
        }
    );
}

export function removeFromBasket(
    basketId: string,
    itemIds: string[]
): Promise<Basket> {
    return api.post<Basket>(`/${EntityName.Basket}/${basketId}/remove`, {
        items: itemIds,
    });
}

// Saved searches -----------------------------------------------------------

export async function getSavedSearches(
    options: {query?: string; url?: string} = {}
): Promise<Page<SavedSearch>> {
    return toPage(
        await api.get<HydraCollection<SavedSearch>>(
            options.url ?? `/${EntityName.SavedSearch}`,
            {
                params: options.url ? undefined : {query: options.query},
            }
        )
    );
}

export function getSavedSearch(id: string): Promise<SavedSearch> {
    return api.get<SavedSearch>(`/${EntityName.SavedSearch}/${id}`);
}

export function postSavedSearch(
    data: Partial<SavedSearch>
): Promise<SavedSearch> {
    return api.post<SavedSearch>(`/${EntityName.SavedSearch}`, data);
}

export function putSavedSearch(
    id: string,
    data: Partial<SavedSearch>
): Promise<SavedSearch> {
    return api.put<SavedSearch>(
        `/${EntityName.SavedSearch}/${id}`,
        toIris(data)
    );
}

export function deleteSavedSearch(id: string): Promise<void> {
    return api.delete(`/${EntityName.SavedSearch}/${id}`);
}

// Display profiles ---------------------------------------------------------

export async function getProfiles(
    options: {query?: string; url?: string} = {}
): Promise<Page<DisplayProfile>> {
    return toPage(
        await api.get<HydraCollection<DisplayProfile>>(
            options.url ?? `/${EntityName.Profile}`,
            {
                params: options.url ? undefined : {query: options.query},
            }
        )
    );
}

export function getProfile(id: string): Promise<DisplayProfile> {
    return api.get<DisplayProfile>(`/${EntityName.Profile}/${id}`);
}

export function postProfile(
    data: Partial<DisplayProfile>
): Promise<DisplayProfile> {
    return api.post<DisplayProfile>(`/${EntityName.Profile}`, data);
}

export function putProfile(
    id: string,
    data: Partial<DisplayProfile>
): Promise<DisplayProfile> {
    return api.put<DisplayProfile>(
        `/${EntityName.Profile}/${id}`,
        toIris(data)
    );
}

export function deleteProfile(id: string): Promise<void> {
    return api.delete(`/${EntityName.Profile}/${id}`);
}

export function addToProfile(
    profileId: string | undefined,
    items: ProfileItem[]
): Promise<DisplayProfile> {
    return api.post<DisplayProfile>(
        `/${EntityName.Profile}/${profileId ?? 'default'}/items`,
        {items}
    );
}

export function removeFromProfile(
    profileId: string,
    itemIds: string[]
): Promise<DisplayProfile> {
    return api.post<DisplayProfile>(
        `/${EntityName.Profile}/${profileId}/remove`,
        {items: itemIds}
    );
}

export function putProfileItem(
    profileId: string,
    itemId: string,
    data: Partial<ProfileItem>
): Promise<ProfileItem> {
    return api.put<ProfileItem>(
        `/${EntityName.Profile}/${profileId}/items/${itemId}`,
        data
    );
}

export function sortProfileItems(
    profileId: string,
    ids: string[]
): Promise<void> {
    return api.post(`/${EntityName.Profile}/${profileId}/sort`, ids);
}

// Renditions ---------------------------------------------------------------

export async function getAssetRenditions(
    assetId: string
): Promise<AssetRendition[]> {
    return toPage(
        await api.get<HydraCollection<AssetRendition>>(
            `/${EntityName.Rendition}`,
            {params: {assetId}}
        )
    ).items;
}

export async function getRenditionDefinitions(options: {
    workspaceIds?: string[];
    workspaceId?: string;
    assetId?: string;
    target?: AssetType;
    query?: string;
    url?: string;
}): Promise<Page<RenditionDefinition>> {
    const {url, query, ...params} = options;

    return toPage(
        await api.get<HydraCollection<RenditionDefinition>>(
            url ?? `/${EntityName.RenditionDefinition}`,
            {
                params: url ? undefined : {...params, name: query},
            }
        )
    );
}

export type RenditionInput = {
    name?: string;
    definitionId?: string;
    sourceFile?: SourceFileInput;
    sourceFileId?: string;
    assetId: string;
    substituted?: boolean;
    force?: boolean;
    multipart?: MultipartUpload;
    buildDefinition?: string;
    writeMetadata?: boolean;
    sourceRenditionId?: string;
};

export function postRendition(data: RenditionInput): Promise<AssetRendition> {
    return api.post<AssetRendition>(`/${EntityName.Rendition}`, data);
}

export function deleteRendition(id: string): Promise<void> {
    return api.delete(`/${EntityName.Rendition}/${id}`);
}

export function putRenditionDefinition(
    id: string,
    data: Partial<RenditionDefinition>
): Promise<RenditionDefinition> {
    const {workspace: _w, ...rest} = data;

    return api.put<RenditionDefinition>(
        `/${EntityName.RenditionDefinition}/${id}`,
        toIris(rest)
    );
}

export function postRenditionDefinition(
    data: Partial<RenditionDefinition>
): Promise<RenditionDefinition> {
    return api.post<RenditionDefinition>(
        `/${EntityName.RenditionDefinition}`,
        toIris(data)
    );
}

export function deleteRenditionDefinition(id: string): Promise<void> {
    return api.delete(`/${EntityName.RenditionDefinition}/${id}`);
}

export function sortRenditionDefinitions(ids: string[]): Promise<void> {
    return api.post(`/${EntityName.RenditionDefinition}/sort`, ids);
}

export async function getRenditionPolicies(
    workspaceId: string
): Promise<Page<RenditionPolicy>> {
    return toPage(
        await api.get<HydraCollection<RenditionPolicy>>(
            `/${EntityName.RenditionPolicy}`,
            {params: {workspaceId}}
        )
    );
}

export function putRenditionPolicy(
    id: string,
    data: Partial<RenditionPolicy>
): Promise<RenditionPolicy> {
    const {workspace: _w, ...rest} = data;

    return api.put<RenditionPolicy>(
        `/${EntityName.RenditionPolicy}/${id}`,
        rest
    );
}

export function postRenditionPolicy(
    data: Partial<RenditionPolicy>
): Promise<RenditionPolicy> {
    return api.post<RenditionPolicy>(
        `/${EntityName.RenditionPolicy}`,
        toIris(data)
    );
}

export function deleteRenditionPolicy(id: string): Promise<void> {
    return api.delete(`/${EntityName.RenditionPolicy}/${id}`);
}

export function getRenditionBuildReference(): Promise<{
    id: string;
    reference: string;
    references: {
        name: string;
        description?: string | null;
        reference: string;
    }[];
}> {
    return api.get('/rendition-build-reference');
}

// Files --------------------------------------------------------------------

export function getFile(id: string): Promise<ApiFile> {
    return api.get<ApiFile>(`/${EntityName.File}/${id}`);
}

export function getFileMetadata(id: string): Promise<ApiFile> {
    return api.get<ApiFile>(`/${EntityName.File}/${id}/metadata`);
}

export async function getFileDuplicates(id: string): Promise<DuplicateAsset[]> {
    const res = await api.get<{duplicates?: DuplicateAsset[]}>(
        `/${EntityName.File}/${id}/duplicates`
    );

    return res.duplicates ?? [];
}

// Shares -------------------------------------------------------------------

export async function getAssetShares(assetId: string): Promise<Share[]> {
    return toPage(
        await api.get<HydraCollection<Share>>(`/${EntityName.Share}`, {
            params: {assetId},
        })
    ).items;
}

export function getPublicShare(id: string, token: string): Promise<Share> {
    return api.get<Share>(`/${EntityName.Share}/${id}/public`, {
        params: {token},
        anonymous: true,
    });
}

export function createShare(
    assetId: string,
    data: Partial<Share> = {}
): Promise<Share> {
    return api.post<Share>(`/${EntityName.Share}`, {
        ...data,
        asset: `/${EntityName.Asset}/${assetId}`,
    });
}

export function deleteShare(id: string): Promise<void> {
    return api.delete(`/${EntityName.Share}/${id}`);
}

// Exports ------------------------------------------------------------------

export function exportAssets(data: {
    assets: string[];
    renditions: string[];
}): Promise<AssetExport> {
    return api.post<AssetExport>('/asset-exports', data);
}

// Attachments --------------------------------------------------------------

export function postAttachment(data: {
    assetId: string;
    attachmentId: string;
    name?: string;
}): Promise<AssetAttachment> {
    return api.post<AssetAttachment>(`/${EntityName.Attachment}`, data);
}

export function putAttachment(
    id: string,
    data: {name?: string}
): Promise<AssetAttachment> {
    return api.put<AssetAttachment>(`/${EntityName.Attachment}/${id}`, data);
}

export function deleteAttachment(id: string): Promise<void> {
    return api.delete(`/${EntityName.Attachment}/${id}`);
}

// Discussion ---------------------------------------------------------------

export async function getThreadMessages(
    threadId: string,
    url?: string
): Promise<Page<ThreadMessage>> {
    return toPage(
        await api.get<HydraCollection<ThreadMessage>>(
            url ?? `/${EntityName.Thread}/${threadId}/messages`
        )
    );
}

export function getMessage(id: string): Promise<ThreadMessage> {
    return api.get<ThreadMessage>(`/${EntityName.Message}/${id}`);
}

export function postMessage(data: {
    threadKey: string;
    threadId?: string;
    content: string;
    attachments?: ThreadMessage['attachments'];
}): Promise<ThreadMessage> {
    return api.post<ThreadMessage>(`/${EntityName.Message}`, data);
}

export function putMessage(
    id: string,
    data: {content: string}
): Promise<ThreadMessage> {
    return api.put<ThreadMessage>(`/${EntityName.Message}/${id}`, data);
}

export function deleteMessage(id: string): Promise<void> {
    return api.delete(`/${EntityName.Message}/${id}`);
}

// Permissions ---------------------------------------------------------------

export async function getUsers(
    query?: string,
    signal?: AbortSignal
): Promise<User[]> {
    return api.get<User[]>('/permissions/users', {
        params: query ? {query} : undefined,
        signal,
    });
}

export async function getGroups(
    query?: string,
    signal?: AbortSignal
): Promise<Group[]> {
    return api.get<Group[]>('/permissions/groups', {
        params: query ? {query} : undefined,
        signal,
    });
}

export async function getAces(
    objectType: string,
    objectId: string
): Promise<Ace[]> {
    const aces = await api.get<Ace[]>('/permissions/aces', {
        params: {
            objectType,
            objectId,
            userIdWildcard: true,
            objectIdWildcard: true,
        },
    });

    return aces
        .map(ace => ({...ace, wildcard: !ace.objectId}))
        .sort((a, b) => {
            const u = (b.userId ? 0 : 1) - (a.userId ? 0 : 1);

            return u !== 0 ? u : (b.wildcard ? 1 : 0) - (a.wildcard ? 1 : 0);
        });
}

export function putAce(data: {
    userType: UserType;
    userId: string | null;
    objectType: string;
    objectId?: string;
    mask: number;
    metadata?: number[];
}): Promise<Ace> {
    return api.put<Ace>('/permissions/ace', data);
}

export function deleteAce(data: {
    userType: UserType;
    userId: string | null;
    objectType: string;
    objectId?: string;
}): Promise<void> {
    return api
        .raw('/permissions/ace', {method: 'delete', json: data})
        .then(() => undefined);
}

// Workflows ----------------------------------------------------------------

export async function getAssetWorkflows(
    assetId: string
): Promise<Page<Workflow>> {
    return toPage(
        await api.get<HydraCollection<Workflow>>(`/${EntityName.Workflow}`, {
            params: {asset: `/${EntityName.Asset}/${assetId}`},
        })
    );
}

export function getWorkflow(id: string): Promise<Workflow> {
    return api.get<Workflow>(`/${EntityName.Workflow}/${id}`);
}

export function rerunWorkflowJob(
    workflowId: string,
    jobId: string
): Promise<Workflow> {
    return api.post<Workflow>(
        `/${EntityName.Workflow}/${workflowId}/jobs/${jobId}/rerun`,
        {}
    );
}

export function cancelWorkflow(workflowId: string): Promise<Workflow> {
    return api.post<Workflow>(
        `/${EntityName.Workflow}/${workflowId}/cancel`,
        {}
    );
}

// Operation tasks ----------------------------------------------------------

export async function getOperationTasks(
    url?: string
): Promise<Page<OperationTask>> {
    return toPage(
        await api.get<HydraCollection<OperationTask>>(
            url ?? `/${EntityName.OperationTask}`
        )
    );
}

export function getOperationTask(id: string): Promise<OperationTask> {
    return api.get<OperationTask>(`/${EntityName.OperationTask}/${id}`);
}

export function runOperationTask(data: {
    task: string;
    payload: Record<string, unknown>;
}): Promise<OperationTask> {
    return api.post<OperationTask>(`/${EntityName.OperationTask}`, data);
}

// CMS pages ----------------------------------------------------------------

export async function getPages(url?: string): Promise<Page<CmsPage>> {
    return toPage(
        await api.get<HydraCollection<CmsPage>>(url ?? `/${EntityName.Page}`)
    );
}

export function getPage(id: string): Promise<CmsPage> {
    return api.get<CmsPage>(`/${EntityName.Page}/${id}`);
}

export function getPageBySlug(slug: string): Promise<CmsPage> {
    return api.get<CmsPage>(`/page-by-slug/${slug}`);
}

export function postPage(data: Partial<CmsPage>): Promise<CmsPage> {
    return api.post<CmsPage>(`/${EntityName.Page}`, data);
}

export function putPage(id: string, data: Partial<CmsPage>): Promise<CmsPage> {
    return api.put<CmsPage>(`/${EntityName.Page}/${id}`, data);
}

export function deletePage(id: string): Promise<void> {
    return api.delete(`/${EntityName.Page}/${id}`);
}

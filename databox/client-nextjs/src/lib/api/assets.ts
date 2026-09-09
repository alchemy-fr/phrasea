import {api} from './http';
import {toPage} from './hydra';
import {
    Asset,
    AssetFileVersion,
    AssetTypeFilter,
    Attribute,
    AttributeBatchAction,
    AttributeBatchActionEnum,
    AttributeDefinition,
    Collection,
    DuplicateAsset,
    EntityName,
    ESDebug,
    ESDocumentState,
    Facets,
    HydraCollection,
    MatomoMediaMetrics,
    Page,
    SearchSuggestion,
} from '@/types/api';
import {multipartUpload, MultipartUpload, type UploadProgress} from './upload';

export type SortWay = 'asc' | 'desc';

export type SearchAssetsOptions = {
    url?: string;
    query?: string;
    conditions?: string[];
    order?: Record<string, SortWay>;
    group?: string[];
    savedSearch?: string;
    context?: {position?: string};
    ids?: string[];
    parents?: string[];
    story?: string;
    workspaces?: string[];
    limit?: number;
    allLocales?: boolean;
};

export type SearchAssetsResult = Page<Asset> & {
    facets: Facets;
    debug?: ESDebug;
};

export async function searchAssets(
    {url, ...params}: SearchAssetsOptions,
    signal?: AbortSignal
): Promise<SearchAssetsResult> {
    const started = performance.now();
    const res = await api.get<
        HydraCollection<
            Asset,
            {'facets': Facets; 'debug:es'?: {query: object; time: number}}
        >
    >(url ?? `/${EntityName.Asset}`, {
        params: url ? undefined : params,
        signal,
    });

    const page = toPage(res);
    const debug = res['debug:es'];

    return {
        ...page,
        facets: res.facets ?? {},
        debug: debug
            ? {
                  query: debug.query,
                  esQueryTime: debug.time,
                  totalResponseTime: Math.round(performance.now() - started),
              }
            : undefined,
    };
}

export async function getSearchSuggestions(
    query: string,
    signal?: AbortSignal
): Promise<Page<SearchSuggestion> & {debug?: object}> {
    const res = await api.get<
        HydraCollection<SearchSuggestion, {'debug:es'?: {query: object}}>
    >(`/${EntityName.Asset}/suggest`, {params: {query}, signal});

    return {...toPage(res), debug: res['debug:es']?.query};
}

export async function resolveEntities(
    iris: string[],
    signal?: AbortSignal
): Promise<Record<string, object | null>> {
    return api.post(
        `/${EntityName.Asset}/entities`,
        {entities: iris},
        {signal}
    );
}

export function getAsset(id: string, signal?: AbortSignal): Promise<Asset> {
    return api.get<Asset>(`/${EntityName.Asset}/${id}`, {signal});
}

export async function getStoryThumbnails(assetId: string): Promise<string[]> {
    const res = await api.get<{thumbnails: string[]}>(
        `/${EntityName.Asset}/${assetId}/story-thumbnails`
    );

    return res.thumbnails;
}

export async function getAssetAttributes(
    assetId: string | string[]
): Promise<Attribute[]> {
    const res = await api.get<HydraCollection<Attribute>>('/attributes', {
        params: {assetId},
    });

    return toPage(res).items;
}

export async function getAssetFileVersions(
    assetId: string
): Promise<Page<AssetFileVersion>> {
    return toPage(
        await api.get<HydraCollection<AssetFileVersion>>(
            '/asset-file-versions',
            {
                params: {assetId},
            }
        )
    );
}

export function deleteAssetFileVersion(id: string): Promise<void> {
    return api.delete(`/asset-file-versions/${id}`);
}

function normalizeActions(
    actions: AttributeBatchAction[]
): AttributeBatchAction[] {
    return actions.map(a =>
        a.action === AttributeBatchActionEnum.Delete
            ? {...a, value: undefined}
            : {...a, origin: 'human' as const}
    );
}

export async function attributeBatchUpdate(
    assetId: string | string[],
    actions: AttributeBatchAction[]
): Promise<Asset> {
    const normalized = normalizeActions(actions);
    if (typeof assetId === 'string') {
        return api.post<Asset>(`/${EntityName.Asset}/${assetId}/attributes`, {
            actions: normalized,
        });
    }

    return api.post<Asset>('/attributes/batch-update', {
        assets: assetId,
        actions: normalized,
    });
}

export function deleteAssetAttribute(id: string): Promise<void> {
    return api.delete(`/attributes/${id}`);
}

export function triggerAssetWorkflow(id: string): Promise<void> {
    return api.put(`/${EntityName.Asset}/${id}/trigger-workflow`, {});
}

export type DeleteAssetsOptions = {
    collections?: string[];
    hardDelete?: boolean;
};

export function deleteAssets(
    ids: string[],
    options: DeleteAssetsOptions = {}
): Promise<void> {
    return api.post(`/${EntityName.Asset}/delete-multiple`, {ids, ...options});
}

export function restoreAssets(ids: string[]): Promise<void> {
    return api.post(`/${EntityName.Asset}/restore-multiple`, {ids});
}

export type PrepareDeleteOutput = {
    canDelete: boolean;
    collections: Collection[];
    shareCount: number;
};

export function prepareDeleteAssets(
    ids: string[]
): Promise<PrepareDeleteOutput> {
    return api.post<PrepareDeleteOutput>(
        `/${EntityName.Asset}/prepare-delete`,
        {ids}
    );
}

export type SourceFileInput = {
    url?: string;
    originalName?: string;
    type?: string;
    isPrivate?: boolean;
    importFile?: boolean;
};

export type AssetInput = {
    name?: string;
    privacy?: number;
    tags?: string[];
    collection?: string;
    workspace?: string;
    isStory?: boolean;
    sourceFileId?: string;
    multipart?: MultipartUpload;
    sourceFile?: SourceFileInput;
    attributes?: AttributeBatchAction[];
    relationship?: {
        source: string;
        type: string;
        sourceFile?: string;
        integration?: string;
    };
};

export function patchAsset(
    id: string,
    data: Partial<AssetInput>
): Promise<Asset> {
    return api.patch<Asset>(`/${EntityName.Asset}/${id}`, data);
}

export function postAsset(
    data: AssetInput,
    options: CreateOptions = {}
): Promise<Asset> {
    return api.post<Asset>(`/${EntityName.Asset}`, data, {
        headers: quietHeaders(options),
    });
}

export type CreateOptions = {
    quiet?: boolean;
    signal?: AbortSignal;
};

function quietHeaders({
    quiet,
}: CreateOptions): Record<string, string | undefined> {
    return quiet
        ? {'X-Webhook-Disabled': 'true', 'X-Notification-Disabled': 'true'}
        : {};
}

/** Destination IRI (`/workspaces/{id}` or `/collections/{id}`) to asset props */
export function destinationToAssetProps(
    destination: string
): Pick<AssetInput, 'workspace' | 'collection'> {
    return destination.startsWith(`/${EntityName.Workspace}/`)
        ? {workspace: destination}
        : {collection: destination};
}

export async function uploadAsset(
    file: File,
    asset: AssetInput,
    {
        onProgress,
        quiet,
        signal,
    }: CreateOptions & {onProgress?: (p: UploadProgress) => void} = {}
): Promise<Asset> {
    const multipart = await multipartUpload(file, {onProgress, signal});

    return api.post<Asset>(
        `/${EntityName.Asset}`,
        {...asset, multipart},
        {headers: quietHeaders({quiet}), signal}
    );
}

export async function importAssetsFromUrls(
    items: {url: string; importFile?: boolean; asset: AssetInput}[],
    options: CreateOptions & {
        story?: {
            name?: string;
            tags?: string[];
            attributes?: AttributeBatchAction[];
        };
    } = {}
): Promise<Asset[]> {
    const res = await api.post<{assets: Asset[]}>(
        `/${EntityName.Asset}/multiple`,
        {
            isStory: !!options.story,
            story: options.story,
            assets: items.map(i => ({
                ...i.asset,
                sourceFile: {url: i.url, importFile: i.importFile},
            })),
        },
        {headers: quietHeaders(options), signal: options.signal}
    );

    return res.assets;
}

export function deleteAssetShortcut(
    assetId: string,
    collectionId: string
): Promise<void> {
    return api.delete(
        `/${EntityName.Asset}/${assetId}/collections/${collectionId}`
    );
}

export function copyAssets(
    ids: string[],
    destination: string,
    byReference: boolean,
    options: {withAttributes?: boolean; withTags?: boolean} = {}
): Promise<void> {
    return api.post(`/${EntityName.Asset}/copy`, {
        ids,
        destination,
        byReference,
        ...options,
    });
}

export function moveAssets(ids: string[], destination: string): Promise<void> {
    return api.post(`/${EntityName.Asset}/move`, {ids, destination});
}

export function bypassQuarantine(id: string): Promise<Asset> {
    return api.post<Asset>(`/${EntityName.Asset}/${id}/quarantine-bypass`, {});
}

export async function getAssetDuplicates(
    id: string
): Promise<DuplicateAsset[]> {
    const res = await api.get<{duplicates?: DuplicateAsset[]}>(
        `/${EntityName.Asset}/${id}/duplicates`
    );

    return res.duplicates ?? [];
}

export function addAsAssetVersion(
    quarantinedAssetId: string,
    targetAssetId: string
): Promise<Asset> {
    return api.post<Asset>(
        `/${EntityName.Asset}/${quarantinedAssetId}/add-as-version`,
        {targetAssetId}
    );
}

export function getAssetMetrics(assetId: string): Promise<MatomoMediaMetrics> {
    return api.get<MatomoMediaMetrics>(
        `/${EntityName.Asset}/${assetId}/metrics`
    );
}

export function getESDocument(
    entity: string,
    id: string
): Promise<ESDocumentState> {
    return api.get<ESDocumentState>(`/${entity}/${id}/es-document`);
}

export function syncESDocument(entity: string, id: string): Promise<void> {
    return api.post(`/${entity}/${id}/es-document-sync`, {});
}

export function follow(
    entity: string,
    id: string,
    topics?: string[]
): Promise<void> {
    return api.post(`/${entity}/${id}/follow`, topics ? {topics} : {});
}

export function unfollow(
    entity: string,
    id: string,
    topics?: string[]
): Promise<void> {
    return api.post(`/${entity}/${id}/unfollow`, topics ? {topics} : {});
}

export function isAssetEligibleForDefinition(
    asset: Asset,
    definition: AttributeDefinition
): boolean {
    const type = asset.storyCollection
        ? AssetTypeFilter.Story
        : AssetTypeFilter.Asset;

    return !(definition.target && (definition.target & type) === 0);
}

export function getAssetNameFromFile(file: File): string {
    return file.name.replace(/\.[^/.]+$/, '').slice(0, 255);
}

export function extractNameFromUrl(url: string): string {
    const parts = url.split('/').filter(Boolean);
    const last = parts[parts.length - 1] ?? url;

    return last.split('?')[0].slice(0, 255);
}

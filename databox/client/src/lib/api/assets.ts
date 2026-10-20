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

/** Latitudes and longitudes of a box */
export type GeoBounds = {
    top: number;
    left: number;
    bottom: number;
    right: number;
};

export type GeoCluster = {
    /** The tile: `zoom/x/y` */
    key: string;
    count: number;
    /** Centroid of the clustered positions */
    lat: number;
    lng: number;
    bounds: GeoBounds;
    /** The asset, when the cluster holds a single one */
    assetId: string | null;
};

export type GeoClusterResult = {
    /** The assets matching the search */
    total: number;
    /** The assets matching the search that have a position */
    geolocated: number;
    /** Box of all the positions, whatever the clustered area */
    bounds: GeoBounds | null;
    clusters: GeoCluster[];
};

/** The options of a search that select its assets: sorting and paging aside */
export type GeoClusterSearch = Pick<
    SearchAssetsOptions,
    'query' | 'conditions' | 'savedSearch' | 'workspaces' | 'parents' | 'story'
>;

export function geoClusterSearch({
    query,
    conditions,
    savedSearch,
    workspaces,
    parents,
    story,
}: SearchAssetsOptions): GeoClusterSearch {
    return {query, conditions, savedSearch, workspaces, parents, story};
}

/**
 * Clusters on a tile grid the positions of the assets matching a search.
 */
export async function getGeoClusters(
    search: GeoClusterSearch,
    {
        field,
        precision,
        bounds,
    }: {
        /** The geo point attribute key (`location_geo-point_s`) */
        field: string;
        /** Tile zoom level (0-29) of the clusters */
        precision: number;
        /** Area to cluster, the whole world when omitted */
        bounds?: GeoBounds;
    },
    signal?: AbortSignal
): Promise<GeoClusterResult> {
    return api.get<GeoClusterResult>(`/${EntityName.Asset}/geo-clusters`, {
        params: {
            ...search,
            field,
            precision,
            bounds: bounds
                ? [bounds.top, bounds.left, bounds.bottom, bounds.right].join(
                      ','
                  )
                : undefined,
        },
        signal,
    });
}

export async function getSearchSuggestions(
    query: string,
    options: {
        /**
         * Only the values of these attribute definitions (an empty query
         * lists them): the same attribute across workspaces
         */
        definitions?: string[];
    } = {},
    signal?: AbortSignal
): Promise<Page<SearchSuggestion> & {debug?: object}> {
    const res = await api.get<
        HydraCollection<SearchSuggestion, {'debug:es'?: {query: object}}>
    >(`/${EntityName.Asset}/suggest`, {
        params: {
            query,
            definition: options.definitions?.length
                ? options.definitions.join(',')
                : undefined,
        },
        signal,
    });

    return {...toPage(res), debug: res['debug:es']?.query};
}

/** A resolved entity, or `{notAllowed: true}` when the user cannot read it */
export type ResolvedEntityValue = Record<string, unknown> & {
    notAllowed?: boolean;
};

export async function resolveEntities(
    iris: string[],
    signal?: AbortSignal
): Promise<Record<string, ResolvedEntityValue | null>> {
    const res = await api.post<{
        entities: Record<string, ResolvedEntityValue | null>;
    }>(`/${EntityName.Asset}/entities`, {entities: iris}, {signal});

    return res.entities ?? {};
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

export type SimilarAssets = {
    items: Asset[];
    /** Similarity score of each asset, by asset ID */
    scores: Record<string, number>;
};

/** The assets visually closest to the given one, the closest first */
export async function getSimilarAssets(
    assetId: string,
    limit: number,
    signal?: AbortSignal
): Promise<SimilarAssets> {
    const res = await api.get<
        HydraCollection<Asset, {scores?: Record<string, number>}>
    >(`/${EntityName.Asset}/${assetId}/similar`, {
        params: {limit},
        signal,
    });

    return {
        items: res.member,
        // An empty PHP array is serialized as a JSON list
        scores: Array.isArray(res.scores) ? {} : (res.scores ?? {}),
    };
}

export async function getAssetAttributes(
    assetId: string
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
    actions: AttributeBatchAction[],
    workspaceId?: string
): Promise<Asset> {
    const normalized = normalizeActions(actions);
    if (typeof assetId === 'string') {
        return api.post<Asset>(`/${EntityName.Asset}/${assetId}/attributes`, {
            actions: normalized,
        });
    }

    return api.post<Asset>('/attributes/batch-update', {
        assets: assetId,
        workspaceId,
        actions: normalized,
    });
}

/** Changes a single attribute value (its origin becomes human) */
export function patchAssetAttribute(
    id: string,
    data: Partial<Attribute>
): Promise<Attribute> {
    return api.patch<Attribute>(`/attributes/${id}`, data);
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
    /** Story creation: existing assets added to the story */
    storyAssets?: string[];
    /** Story creation: the one of `storyAssets` whose file is the cover */
    storyCover?: string;
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

/** The source file of `assetId` (an item of the story) becomes the story cover */
export function setStoryCover(
    storyId: string,
    assetId: string
): Promise<Asset> {
    return api.put<Asset>(`/${EntityName.Asset}/${storyId}/story-cover`, {
        assetId,
    });
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

/**
 * Links assets to a collection (or to a story, given the story asset's IRI)
 * without touching their other collections. Idempotent: assets already in
 * the destination are skipped. The assets must belong to the destination's
 * workspace.
 */
export function addAssetsToCollection(
    ids: string[],
    destination: string
): Promise<void> {
    return api.post(`/${EntityName.Asset}/add-to-collection`, {
        ids,
        destination,
    });
}

/**
 * Moves an asset to a zero-based rank inside a collection (or a story, given
 * the story asset's IRI).
 */
export function setAssetPosition(
    id: string,
    destination: string,
    position: number
): Promise<void> {
    return api.put(`/${EntityName.Asset}/${id}/position`, {
        destination,
        position,
    });
}

/** Unlinks an asset from a collection (or from a story's collection) */
export function removeAssetFromCollection(
    id: string,
    collectionId: string
): Promise<void> {
    return api.delete(`/${EntityName.Asset}/${id}/collections/${collectionId}`);
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

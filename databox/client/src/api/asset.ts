import apiClient from './api-client';
import {
    Asset,
    AssetFileVersion,
    AssetTypeFilter,
    Attribute,
    AttributeDefinition,
    Collection,
    ESDocumentState,
    Share,
} from '../types';
import {AxiosRequestConfig} from 'axios';
import {TFacets} from '../components/Media/Asset/Facets';
import {AttributeBatchAction, AttributeBatchActionEnum} from './types.ts';
import {SortWay} from './common.ts';
import {
    getHydraCollection,
    HydraCollectionResponse,
    MultipartUpload,
    NormalizedCollectionResponse,
} from '@alchemy/api';
import {
    multipartUpload,
    MultipartUploadOptions,
} from '@alchemy/api/src/multiPartUpload.ts';
import {promiseConcurrency} from '../lib/promises.ts';
import {useUploadStore} from '../store/uploadStore.ts';
import {
    CreateAssetsOptions,
    FileInputFromUrl,
    FileOrUrl,
    SourceFileInput,
} from './file.ts';

export interface GetAssetOptions {
    url?: string;
    query?: string;
    workspaces?: string[];
    ids?: string[];
    parents?: string[];
    story?: string;
    conditions?: string[];
    order?: Record<string, SortWay>;
    group?: string[] | undefined;
    context?:
        | {
              position?: string | undefined;
          }
        | undefined;
    allLocales?: boolean;
}

export type ESDebug = {
    query: object;
    esQueryTime: number;
    totalResponseTime: number;
};

export async function getStoryThumbnails(assetId: string): Promise<string[]> {
    const res = await apiClient.get(`/assets/${assetId}/story-thumbnails`);

    return res.data.thumbnails;
}

export function getAssetsHydraCollection(
    response: HydraCollectionResponse<
        Asset,
        {
            facets: TFacets;
        }
    >
) {
    return {
        ...getHydraCollection(response),
        facets: response.facets,
    };
}

export async function getAssets(
    options: GetAssetOptions,
    requestConfig?: AxiosRequestConfig
): Promise<
    NormalizedCollectionResponse<
        Asset,
        {
            facets: TFacets;
            debug: ESDebug;
        }
    >
> {
    const res = options.url
        ? await apiClient.get(options.url, requestConfig)
        : await apiClient.get('/assets', {
              params: options,
              ...requestConfig,
          });

    return {
        ...getAssetsHydraCollection(res.data),
        debug: {
            query: res.data['debug:es'].query,
            esQueryTime: res.data['debug:es'].time,
            totalResponseTime: res.config.meta!.responseTime!,
        },
    };
}

export type SearchSuggestion = {
    id: string;
    name: string;
    hl: string;
    t: string;
};

export async function getSearchSuggestions(
    query: string,
    requestConfig?: AxiosRequestConfig
): Promise<
    NormalizedCollectionResponse<
        SearchSuggestion,
        {
            debug: ESDebug;
        }
    >
> {
    const res = await apiClient.get('/assets/suggest', {
        params: {
            query,
        },
        ...requestConfig,
    });

    return {
        ...getHydraCollection<SearchSuggestion>(res.data),
        debug: {
            query: res.data['debug:es'].query,
            esQueryTime: res.data['debug:es'].time,
            totalResponseTime: res.config.meta!.responseTime!,
        },
    };
}

export async function resolveEntities(
    entities: string[],
    requestConfig?: AxiosRequestConfig
): Promise<Record<string, object>> {
    const res = await apiClient.post('/assets/entities', {
        entities,
        ...requestConfig,
    });

    return res.data;
}

export async function getAsset(id: string): Promise<Asset> {
    return (await apiClient.get(`/assets/${id}`)).data;
}

export async function getESDocument(
    entity: string,
    id: string
): Promise<ESDocumentState> {
    return (await apiClient.get(`/${entity}/${id}/es-document`)).data;
}

export async function syncESDocument(
    entity: string,
    id: string
): Promise<void> {
    await apiClient.post(`/${entity}/${id}/es-document-sync`, {});
}

export async function getAssetShares(assetId: string): Promise<Share[]> {
    return (
        await apiClient.get(`/shares`, {
            params: {
                assetId,
            },
        })
    ).data['hydra:member'];
}

export async function getPublicShare(
    id: string,
    token: string
): Promise<Share> {
    return (
        await apiClient.get(`/shares/${id}/public`, {
            params: {
                token,
            },
        })
    ).data;
}

export async function createAssetShare(
    assetId: string,
    data: Partial<Share> = {}
): Promise<Share> {
    const res = (
        await apiClient.post(`/shares`, {
            ...data,
            asset: `/assets/${assetId}`,
        })
    ).data;

    return res;
}

export async function removeAssetShare(assetId: string): Promise<void> {
    await apiClient.delete(`/shares/${assetId}`);
}

export async function getAssetAttributes(
    assetId: string | string[]
): Promise<Attribute[]> {
    const res = await apiClient.get(`/attributes`, {
        params: {
            assetId,
        },
    });

    return res.data['hydra:member'];
}

const assetFileVersionEntity = 'asset-file-versions';

export async function getAssetFileVersions(
    assetId: string | string[]
): Promise<NormalizedCollectionResponse<AssetFileVersion>> {
    const res = await apiClient.get(`/${assetFileVersionEntity}`, {
        params: {
            assetId,
        },
    });

    return getHydraCollection(res.data);
}

export async function deleteAssetFileVersion(id: string): Promise<void> {
    await apiClient.delete(`${assetFileVersionEntity}/${id}`);
}

export async function attributeBatchUpdate(
    assetId: string | string[],
    actions: AttributeBatchAction[]
): Promise<Asset> {
    actions = normalizeActions(actions);

    if (typeof assetId === 'string') {
        return (
            await apiClient.post(`/assets/${assetId}/attributes`, {
                actions,
            })
        ).data;
    } else {
        return (
            await apiClient.post(`/attributes/batch-update`, {
                assets: assetId,
                actions,
            })
        ).data;
    }
}

export async function workspaceAttributeBatchUpdate(
    workspaceId: string,
    actions: AttributeBatchAction[]
): Promise<Asset> {
    return (
        await apiClient.post(`/attributes/batch-update`, {
            workspaceId,
            actions: normalizeActions(actions),
        })
    ).data;
}

function normalizeActions(
    actions: AttributeBatchAction[]
): AttributeBatchAction[] {
    return actions.map(a => {
        if (a.action === AttributeBatchActionEnum.Delete) {
            return {
                ...a,
                value: undefined,
            };
        }

        return {
            ...a,
            origin: 'human',
        };
    });
}

export async function deleteAssetAttribute(id: string): Promise<void> {
    await apiClient.delete(`/attributes/${id}`);
}

export async function triggerAssetWorkflow(id: string): Promise<void> {
    await apiClient.put(`/assets/${id}/trigger-workflow`, {});
}

export async function deleteAsset(id: string): Promise<void> {
    await apiClient.delete(`/assets/${id}`);
}

type DeleteOptions = {
    collections?: string[];
    hardDelete?: boolean;
};

export async function deleteAssets(
    ids: string[],
    deleteOptions: DeleteOptions = {}
): Promise<void> {
    await apiClient.post(`/assets/delete-multiple`, {
        ids,
        ...deleteOptions,
    });
}

export async function restoreAssets(ids: string[]): Promise<void> {
    await apiClient.post(`/assets/restore-multiple`, {
        ids,
    });
}

export type PrepareDeleteAssetsOutput = {
    canDelete: boolean;
    collections: Collection[];
    shareCount: number;
};

export async function prepareDeleteAssets(
    ids: string[]
): Promise<PrepareDeleteAssetsOutput> {
    const res = await apiClient.post(`/assets/prepare-delete`, {
        ids,
    });
    return res.data;
}

export async function putAsset(
    id: string,
    data: Partial<AssetApiInput>
): Promise<Asset> {
    const res = await apiClient.put(`/assets/${id}`, data, {
        headers: {
            'Content-Type': 'application/merge-patch+json',
        },
    });

    return res.data;
}

export type AssetApiInput = {
    title?: string;
    privacy?: number;
    tags?: string[];
    collection?: string;
    workspace?: string;
    sequence?: number;
    isStory?: boolean;
    sourceFileId?: string;
    multipart?: MultipartUpload;
    sourceFile?: SourceFileInput;
};

export type NewAssetInput = {
    relationship?:
        | {
              source: string;
              type: string;
              sourceFile?: string | undefined;
              integration?: string | undefined;
          }
        | undefined;
    attributes?: AttributeBatchAction[] | undefined;
} & AssetApiInput;

export async function postAsset(data: NewAssetInput): Promise<Asset> {
    const res = await apiClient.post(`/assets`, data);

    return res.data;
}

type InputFile = {
    asset: NewAssetInput;
} & FileOrUrl;

type InputUploadFile = {
    file: File;
    asset: NewAssetInput;
};

type InputAssetImport = {
    asset: NewAssetInput;
} & FileInputFromUrl;

type FileBeingUploaded = {
    id: string;
} & InputUploadFile;

type Destination = string;

function computeAssetPropsFromDestination(destination: Destination) {
    if (destination.startsWith('/workspaces/')) {
        return {workspace: destination};
    } else {
        return {collection: destination};
    }
}

export async function uploadAssets(
    files: InputFile[],
    destination: Destination,
    options: CreateAssetsOptions = {}
): Promise<Asset[]> {
    const uploadState = useUploadStore.getState();

    const uploads: FileBeingUploaded[] = files
        .filter(f => Boolean(f.file))
        .map(
            f =>
                ({
                    ...f,
                    id: crypto.randomUUID(),
                }) as FileBeingUploaded
        );

    uploads.forEach(f => {
        uploadState.addUpload({
            id: f.id,
            file: f.file,
            progress: 0,
        });
    });

    const storyAsset = await createStoryAssetIfNeeded(destination, options);

    return await promiseConcurrency(
        uploads.map(f => {
            return () =>
                uploadAsset(
                    {
                        ...f,
                        asset: {
                            ...f.asset,
                            ...(storyAsset
                                ? {
                                      collection:
                                          storyAsset?.storyCollection!['@id'],
                                  }
                                : computeAssetPropsFromDestination(
                                      destination
                                  )),
                        },
                    },
                    {
                        ...options,
                        isStory: false,
                        story: undefined,
                    },
                    {
                        onProgress: event => {
                            const progress = event.loaded / event.total!;
                            uploadState.uploadProgress({
                                id: f.id,
                                file: f.file!,
                                progress,
                            });
                        },
                    }
                ).catch((error: any) => {
                    uploadState.uploadError({
                        id: f.id,
                        file: f.file!,
                        progress: 1,
                        error: error.toString(),
                    });
                    throw error;
                });
        }),
        2
    );
}

function getStoryPropsFromOptions(options: CreateAssetsOptions) {
    const {story: storyOptions} = options;

    return {
        title: storyOptions?.title,
        tags: storyOptions?.tags || [],
        attributes: storyOptions?.attributes,
    };
}

async function createStoryAssetIfNeeded(
    destination: Destination,
    options: CreateAssetsOptions
): Promise<Asset | undefined> {
    if (options.isStory) {
        return await postAsset({
            isStory: true,
            ...getStoryPropsFromOptions(options),
            ...(destination
                ? computeAssetPropsFromDestination(destination)
                : {}),
        });
    }
    return undefined;
}

export async function importAssets(
    files: InputAssetImport[],
    destination: Destination,
    options: CreateAssetsOptions = {}
): Promise<Asset[]> {
    const destProps = computeAssetPropsFromDestination(destination);

    const res: {
        assets: Asset[];
    } = await apiClient.post(
        `/assets/multiple`,
        {
            isStory: options.isStory,
            story: options.isStory
                ? getStoryPropsFromOptions(options)
                : undefined,
            assets: files.map(f => ({
                ...destProps,
                ...f.asset,
                sourceFile: {
                    url: f.url,
                    importFile: f.importFile,
                },
            })),
        },
        {
            ...options.config,
            headers: {
                ...(options.quiet
                    ? {
                          'X-Webhook-Disabled': 'true',
                          'X-Notification-Disabled': 'true',
                      }
                    : {}),
                ...options.config?.headers,
            },
        }
    );

    return res.assets;
}

export async function uploadAsset(
    data: InputUploadFile,
    options: CreateAssetsOptions = {},
    multipartUploadOptions: MultipartUploadOptions = {}
): Promise<Asset> {
    const multipart = await multipartUpload(
        apiClient,
        data.file,
        multipartUploadOptions
    );

    return (
        await apiClient.post(
            `/assets`,
            {
                ...data.asset,
                multipart,
            },
            {
                ...options.config,
                headers: {
                    ...(options.quiet
                        ? {
                              'X-Webhook-Disabled': 'true',
                              'X-Notification-Disabled': 'true',
                          }
                        : {}),
                    ...options.config?.headers,
                },
            }
        )
    ).data;
}

export async function deleteAssetShortcut(
    assetId: string,
    collectionId: string
): Promise<void> {
    await apiClient.delete(`/assets/${assetId}/collections/${collectionId}`);
}

export function isAssetEligibleForAttributeDefinition(
    asset: Asset,
    definition: AttributeDefinition
): boolean {
    const type = asset.storyCollection
        ? AssetTypeFilter.Story
        : AssetTypeFilter.Asset;

    return !(definition.target && (definition.target & type) === 0);
}

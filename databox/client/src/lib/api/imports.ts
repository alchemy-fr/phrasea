import {api} from './http';
import {toPage} from './hydra';
import {multipartUpload, type UploadProgress} from './upload';
import {
    AssetImport,
    AssetImportError,
    AssetImportFormat,
    EntityName,
    HydraCollection,
    Page,
    Privacy,
} from '@/types/api';
import {iri} from '@/lib/utils/iri';

export const ASSET_IMPORTS_PAGE_SIZE = 20;
export const ASSET_IMPORT_ERRORS_PAGE_SIZE = 50;

export type CreateAssetImportOptions = {
    workspaceId: string;
    collectionId?: string;
    format: AssetImportFormat;
    privacy?: Privacy;
    onProgress?: (progress: UploadProgress) => void;
    signal?: AbortSignal;
};

/**
 * Uploads the instructions file to the storage, then registers the import:
 * the assets are created asynchronously by the API.
 */
export async function createAssetImport(
    file: File,
    {
        workspaceId,
        collectionId,
        format,
        privacy,
        onProgress,
        signal,
    }: CreateAssetImportOptions
): Promise<AssetImport> {
    const multipart = await multipartUpload(file, {onProgress, signal});

    return api.post<AssetImport>(
        `/${EntityName.AssetImport}`,
        {
            workspace: iri(EntityName.Workspace, workspaceId),
            collection: collectionId
                ? iri(EntityName.Collection, collectionId)
                : undefined,
            format,
            privacy,
            originalName: file.name,
            multipart,
        },
        {signal}
    );
}

/** Imports of a workspace, newest first */
export async function getAssetImports(
    workspaceId: string,
    page = 1
): Promise<Page<AssetImport>> {
    return toPage(
        await api.get<HydraCollection<AssetImport>>(
            `/${EntityName.AssetImport}`,
            {
                params: {
                    workspace: workspaceId,
                    page,
                    limit: ASSET_IMPORTS_PAGE_SIZE,
                },
            }
        )
    );
}

export function getAssetImport(id: string): Promise<AssetImport> {
    return api.get<AssetImport>(`/${EntityName.AssetImport}/${id}`);
}

/** Entries rejected by an import, ordered by line */
export async function getAssetImportErrors(
    id: string,
    page = 1
): Promise<Page<AssetImportError>> {
    return toPage(
        await api.get<HydraCollection<AssetImportError>>(
            `/${EntityName.AssetImport}/${id}/errors`,
            {params: {page, limit: ASSET_IMPORT_ERRORS_PAGE_SIZE}}
        )
    );
}

/** Deletes the tracking and the stored file, never the created assets */
export function deleteAssetImport(id: string): Promise<void> {
    return api.delete(`/${EntityName.AssetImport}/${id}`);
}

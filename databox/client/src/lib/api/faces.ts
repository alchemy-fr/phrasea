import {api} from './http';
import {toPage} from './hydra';
import {
    AssetFace,
    EntityName,
    FaceIdentities,
    FaceIdentityOrigin,
    HydraCollection,
    Page,
} from '@/types/api';

export type FaceListOptions = {
    /** Next page URL, instead of the parameters */
    url?: string;
    /** Workspace ID (every readable workspace without it) */
    workspace?: string;
    /** Faces of this person (exact name) */
    identity?: string;
    /** Named or unnamed faces only */
    identified?: boolean;
    origin?: FaceIdentityOrigin;
    asset?: string;
    /** Text the name must contain */
    query?: string;
    limit?: number;
};

export async function getFaces(
    {url, ...params}: FaceListOptions,
    signal?: AbortSignal
): Promise<Page<AssetFace>> {
    return toPage(
        await api.get<HydraCollection<AssetFace>>(
            url ?? `/${EntityName.AssetFace}`,
            {params: url ? undefined : params, signal}
        )
    );
}

export function getFace(id: string): Promise<AssetFace> {
    return api.get<AssetFace>(`/${EntityName.AssetFace}/${id}`);
}

/** The persons named in the workspace(s), and the number of unnamed faces */
export function getFaceIdentities(
    params: {workspace?: string; query?: string} = {},
    signal?: AbortSignal
): Promise<FaceIdentities> {
    return api.get<FaceIdentities>(`/${EntityName.AssetFace}/identities`, {
        params,
        signal,
    });
}

/**
 * Names the face (a null name removes it). The name is also applied to the
 * similar faces of the workspace, asynchronously, when the integration
 * propagates identities.
 */
export function identifyFace(
    id: string,
    identity: string | null
): Promise<AssetFace> {
    return api.post<AssetFace>(`/${EntityName.AssetFace}/${id}/identity`, {
        identity,
    });
}

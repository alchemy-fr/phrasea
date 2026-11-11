import {api} from './http';
import {publicationKeys} from './publications';
import type {
    HydraCollection,
    PublicationShare,
    PublicShareResolution,
} from '@/types/api';

export const shareKeys = {
    list: (publicationId: string) =>
        [...publicationKeys.all, publicationId, 'shares'] as const,
};

export type ShareInput = {
    name?: string | null;
    /** No end when null */
    expiresAt?: string | null;
};

/** The share links of a publication, the most recent first */
export async function getPublicationShares(
    publicationId: string
): Promise<PublicationShare[]> {
    const response = await api.get<HydraCollection<PublicationShare>>(
        `/publications/${encodeURIComponent(publicationId)}/shares`
    );

    return response.member;
}

export async function createPublicationShare(
    publicationId: string,
    data: ShareInput
): Promise<PublicationShare> {
    return api.post<PublicationShare>(
        `/publications/${encodeURIComponent(publicationId)}/shares`,
        data
    );
}

export async function updatePublicationShare(
    id: string,
    data: ShareInput
): Promise<PublicationShare> {
    return api.patch<PublicationShare>(
        `/publication-shares/${encodeURIComponent(id)}`,
        data
    );
}

export async function revokePublicationShare(
    id: string
): Promise<PublicationShare> {
    return api.post<PublicationShare>(
        `/publication-shares/${encodeURIComponent(id)}/revoke`
    );
}

export async function deletePublicationShare(id: string): Promise<void> {
    await api.delete(`/publication-shares/${encodeURIComponent(id)}`);
}

/** The publication opened by a share link; a 404 for an invalid one */
export async function resolveShare(
    token: string
): Promise<PublicShareResolution> {
    return api.get<PublicShareResolution>(
        `/publication-shares/resolve/${encodeURIComponent(token)}`,
        {anonymous: true}
    );
}

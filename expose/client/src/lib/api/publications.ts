import {api} from './http';
import {toPage} from './hydra';
import {getPasswordsHeader} from './passwords';
import type {
    Asset,
    HydraCollection,
    Page,
    Publication,
    PublicationConfig,
    PublicationProfile,
} from '@/types/api';

function passwordHeaders(): Record<string, string | undefined> {
    return {'X-Passwords': getPasswordsHeader()};
}

export const publicationKeys = {
    all: ['publications'] as const,
    list: (params: PublicationListParams) =>
        [...publicationKeys.all, 'list', params] as const,
    detail: (id: string) => [...publicationKeys.all, 'detail', id] as const,
};

export const profileKeys = {
    all: ['profiles'] as const,
    list: (params: Record<string, unknown> = {}) =>
        [...profileKeys.all, 'list', params] as const,
    detail: (id: string) => [...profileKeys.all, 'detail', id] as const,
};

export const assetKeys = {
    detail: (id: string) => ['assets', id] as const,
};

export type PublicationSort = 'recent' | 'name';

export type PublicationListParams = {
    sort?: PublicationSort;
    /** Every publication, not only the root ones */
    flatten?: boolean;
    /** The ones the user can edit */
    editable?: boolean;
    query?: string;
};

function listQuery({
    sort = 'recent',
    flatten,
    editable,
    query,
}: PublicationListParams): Record<string, unknown> {
    return {
        order: sort === 'name' ? {title: 'asc'} : {createdAt: 'desc'},
        flatten: flatten || undefined,
        editable: editable || undefined,
        query: query || undefined,
    };
}

export async function getPublications(
    params: PublicationListParams,
    next?: string
): Promise<Page<Publication>> {
    return toPage(
        await api.get<HydraCollection<Publication>>(
            next ?? '/publications',
            next ? undefined : {params: listQuery(params)}
        )
    );
}

/** A publication by slug or id; `authorized: false` without access to it */
export async function getPublication(id: string): Promise<Publication> {
    return api.get<Publication>(`/publications/${encodeURIComponent(id)}`, {
        headers: passwordHeaders(),
    });
}

export type PublicationInput = {
    title?: string;
    slug?: string | null;
    parent?: string | null;
    profile?: string | null;
    cover?: string | null;
    config?: PublicationConfig;
};

export async function patchPublication(
    id: string,
    data: PublicationInput
): Promise<Publication> {
    return api.patch<Publication>(`/publications/${id}`, data);
}

export async function deletePublication(id: string): Promise<void> {
    await api.delete(`/publications/${id}`);
}

export async function getAsset(id: string): Promise<Asset> {
    return api.get<Asset>(`/assets/${encodeURIComponent(id)}`, {
        headers: passwordHeaders(),
    });
}

export function logPublicationView(id: string): void {
    api.post(`/logs/publication-view/${id}`, undefined, {
        headers: passwordHeaders(),
    }).catch(() => undefined);
}

/** The API logs only the views of the assets the reader may see */
export function logAssetView(id: string): void {
    api.post(`/logs/asset-view/${id}`, undefined, {
        headers: passwordHeaders(),
    }).catch(() => undefined);
}

/**
 * Asks for a download link sent by email (`…/download-request`); the API
 * refuses it while the download terms of the publication are not accepted.
 */
export async function requestDownload(
    url: string,
    email: string,
    acceptTerms: boolean
): Promise<void> {
    await api.post(url, acceptTerms ? {email, acceptTerms: true} : {email}, {
        anonymous: true,
    });
}

/**
 * The archive behind a (signed) archive download URL: its URL once built,
 * `preparing` while the API has it built.
 */
export type ArchiveStatus =
    {status: 'preparing'} | {status: 'ready'; url: string};

export async function getArchiveStatus(url: string): Promise<ArchiveStatus> {
    return api.get<ArchiveStatus>(url, {
        anonymous: true,
        // Rather than the redirection to the archive, which would download it here
        headers: {Accept: 'application/json'},
    });
}

/** Asks for the link to the archive of the publication by email */
export async function requestArchiveByEmail(
    publicationId: string,
    email: string,
    acceptTerms: boolean
): Promise<void> {
    await api.post(
        `/publications/${encodeURIComponent(publicationId)}/archive/download-request`,
        acceptTerms ? {email, acceptTerms: true} : {email},
        {headers: passwordHeaders()}
    );
}

/** The file of a publication to download */
export type DownloadTarget =
    {assetId: string} | {subDefId: string} | {archive: true};

/**
 * A short-lived URL downloading the file right away, given by the API once
 * the download terms are accepted.
 */
export async function getDownloadUrl(
    publicationId: string,
    target: DownloadTarget
): Promise<string> {
    const {url} = await api.post<{url: string}>(
        `/publications/${encodeURIComponent(publicationId)}/download-url`,
        {...target, acceptTerms: true},
        {headers: passwordHeaders()}
    );

    return url;
}

export async function getProfiles(
    next?: string,
    params: {query?: string} = {}
): Promise<Page<PublicationProfile>> {
    return toPage(
        await api.get<HydraCollection<PublicationProfile>>(
            next ?? '/publication-profiles',
            next
                ? undefined
                : {
                      params: {
                          order: {name: 'asc'},
                          query: params.query || undefined,
                      },
                  }
        )
    );
}

export async function getProfile(id: string): Promise<PublicationProfile> {
    return api.get<PublicationProfile>(`/publication-profiles/${id}`);
}

export type ProfileInput = {
    name: string;
    config: PublicationConfig;
};

export async function createProfile(
    data: ProfileInput
): Promise<PublicationProfile> {
    return api.post<PublicationProfile>('/publication-profiles', data);
}

export async function patchProfile(
    id: string,
    data: ProfileInput
): Promise<PublicationProfile> {
    return api.patch<PublicationProfile>(`/publication-profiles/${id}`, data);
}

export async function deleteProfile(id: string): Promise<void> {
    await api.delete(`/publication-profiles/${id}`);
}

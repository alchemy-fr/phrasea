import {api, type RequestOptions} from './http';
import {fetchAllPages, toPage} from './hydra';
import {routes} from '@/lib/routes';
import type {
    HydraCollection,
    Page,
    UploadLink,
    UploadLinkInput,
    UploadLinkSession,
} from '@/types/api';

/**
 * The calls made with an upload link: its token instead of the session of a
 * signed-in user.
 */
export function uploadLinkRequestOptions(token: string): RequestOptions {
    return {
        anonymous: true,
        headers: {Authorization: `UploadLink ${token}`},
    };
}

/** The API refuses a link with one of these codes (401) */
export type UploadLinkErrorCode =
    'upload_link_invalid' | 'upload_link_disabled' | 'upload_link_expired';

/** A file would go over a limit of the link (400) */
export const UPLOAD_LINK_QUOTA_EXCEEDED = 'upload_link_quota_exceeded';

/** The link the token opens, what is left of its limits included */
export function getUploadLinkSession(
    token: string
): Promise<UploadLinkSession> {
    return api.get<UploadLinkSession>(
        '/upload-links/current',
        uploadLinkRequestOptions(token)
    );
}

// -- Management ----------------------------------------------------------

function getUploadLinksPage(
    params: {target?: string} = {},
    nextUrl?: string
): Promise<Page<UploadLink>> {
    return api
        .get<HydraCollection<UploadLink>>(nextUrl ?? '/upload-links', {
            params: nextUrl ? undefined : params,
        })
        .then(toPage);
}

/** The links of the user, every link for an admin */
export function getUploadLinks(
    params: {target?: string} = {}
): Promise<UploadLink[]> {
    return fetchAllPages(getUploadLinksPage(params), next =>
        getUploadLinksPage(undefined, next)
    );
}

export function createUploadLink(input: UploadLinkInput): Promise<UploadLink> {
    return api.post<UploadLink>('/upload-links', input);
}

export function updateUploadLink(
    id: string,
    input: Omit<UploadLinkInput, 'target'>
): Promise<UploadLink> {
    return api.patch<UploadLink>(`/upload-links/${id}`, input);
}

export function deleteUploadLink(id: string): Promise<void> {
    return api.delete(`/upload-links/${id}`);
}

/** The URL to hand out: anyone holding it can upload to the target */
export function getUploadLinkUrl(token: string, origin: string): string {
    return `${origin}${routes.uploadLink(token)}`;
}

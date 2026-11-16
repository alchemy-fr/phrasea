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
 * Sent with the token of a link: tells apart the people holding the same
 * link, the multipart uploads one of them starts are theirs only.
 */
export const UPLOAD_SESSION_HEADER = 'X-Upload-Session';

const UPLOAD_SESSION_KEY = 'upl.upload-session';

let memoryUploadSessionId: string | undefined;

/**
 * The upload session of this browser tab: a UUID kept in sessionStorage, so
 * that a reload keeps the uploads it started (in memory when unavailable).
 */
export function getUploadSessionId(): string {
    try {
        const stored = sessionStorage.getItem(UPLOAD_SESSION_KEY);
        if (stored) {
            return stored;
        }
        const id = crypto.randomUUID();
        sessionStorage.setItem(UPLOAD_SESSION_KEY, id);

        return id;
    } catch {
        memoryUploadSessionId ??= crypto.randomUUID();

        return memoryUploadSessionId;
    }
}

/**
 * The calls made with an upload link: its token instead of the session of a
 * signed-in user.
 */
export function uploadLinkRequestOptions(
    token: string,
    sessionId: string = getUploadSessionId()
): RequestOptions {
    return {
        anonymous: true,
        headers: {
            Authorization: `UploadLink ${token}`,
            [UPLOAD_SESSION_HEADER]: sessionId,
        },
    };
}

/**
 * The API refuses a link with one of these codes: 401, or 403 for a link
 * whose creator lost the right to create it (`upload_link_revoked`)
 */
export type UploadLinkErrorCode =
    | 'upload_link_invalid'
    | 'upload_link_disabled'
    | 'upload_link_expired'
    | 'upload_link_revoked';

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

/** A new token: the URL handed out so far stops working */
export function regenerateUploadLinkToken(id: string): Promise<UploadLink> {
    return api.post<UploadLink>(`/upload-links/${id}/regenerate-token`);
}

export function deleteUploadLink(id: string): Promise<void> {
    return api.delete(`/upload-links/${id}`);
}

/** The URL to hand out: anyone holding it can upload to the target */
export function getUploadLinkUrl(token: string, origin: string): string {
    return `${origin}${routes.uploadLink(token)}`;
}

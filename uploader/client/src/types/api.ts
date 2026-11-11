/**
 * Uploader API contracts (API Platform / Hydra).
 * These types mirror the JSON payloads returned by `uploader/api`.
 */

export interface HydraObject {
    '@id': string;
    '@type': string;
}

export type HydraCollection<T, E extends object = object> = {
    totalItems: number;
    member: T[];
    view?: {
        first?: string;
        previous?: string;
        next?: string;
        last?: string;
    };
} & E;

export type Page<T, E extends object = object> = {
    total: number;
    items: T[];
    next?: string;
    previous?: string;
} & E;

/** `GET /me` */
export type UploaderUser = {
    user_id: string;
    email?: string;
    username: string;
    permissions: {
        form_schema: boolean;
        target_data: boolean;
        /** Creating upload links, and managing one's own */
        upload_link: boolean;
    };
};

export type Target = HydraObject & {
    id: string;
    slug?: string | null;
    name: string;
    description?: string | null;
    createdAt?: string;
    /** It has a form, read with `GET /targets/{id}/form` */
    hasForm: boolean;
    /** It has data, read with `GET /targets/{id}/data` */
    hasData: boolean;
};

/**
 * The fixed data of a target, merged on commit into the form data of every
 * batch (they take precedence over what users enter).
 */
export type TargetData = Record<string, unknown>;

/** Asset created by `POST /assets` once a file is fully uploaded */
export type UploadedAsset = {
    id: string;
};

/**
 * Field errors answered by `POST /form/validate` and `POST /downloads`: the
 * property path (`_form` for the whole form) => its messages.
 */
export type FormErrors = Record<string, string[]>;

export type UploadLinkStatus = 'active' | 'disabled' | 'expired' | 'exhausted';

/**
 * A secret URL letting someone without an account upload to a target
 * (`/upload-links`), within limits counted over every commit made with it.
 * The API leaves out the properties that are null.
 */
export type UploadLink = HydraObject & {
    id: string;
    name?: string | null;
    token: string;
    enabled: boolean;
    expiresAt?: string | null;
    maxAssets?: number | null;
    /** In bytes */
    maxTotalSize?: number | null;
    usedAssets: number;
    usedSize: number;
    ownerId: string;
    lastCommitAt?: string | null;
    createdAt: string;
    updatedAt?: string;
    status: UploadLinkStatus;
    target: {id: string; name: string};
};

/** What can be written on a link; its target only when it is created */
export type UploadLinkInput = {
    target?: string;
    name?: string | null;
    enabled?: boolean;
    expiresAt?: string | null;
    maxAssets?: number | null;
    maxTotalSize?: number | null;
};

/** `GET /upload-links/current`: the link the request is authenticated with */
export type UploadLinkSession = {
    id: string;
    name: string | null;
    target: {id: string; name: string; description: string | null};
    expiresAt: string | null;
    maxAssets: number | null;
    maxTotalSize: number | null;
    usedAssets: number;
    usedSize: number;
    /** What was entered in the form of the last commit made with the link */
    lastFormData: Record<string, unknown> | null;
};

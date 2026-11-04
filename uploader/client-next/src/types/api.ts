/**
 * Uploader API contracts (API Platform / Hydra).
 * These types mirror the JSON payloads returned by `uploader/api`.
 */

import type {LiFormSchema} from '@/features/liform/types';

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
    };
};

export type Target = HydraObject & {
    id: string;
    slug?: string | null;
    name: string;
    description?: string | null;
    createdAt?: string;
};

/** Matches `FormSchema::LOCALE_MODE_*` of the API */
export enum LocaleMode {
    NoLocale = 0,
    UserAgent = 1,
    Forced = 2,
}

export type FormSchema = HydraObject & {
    id: string;
    target: Target;
    locale?: string | null;
    localeMode: LocaleMode;
    data: LiFormSchema;
};

export type TargetParams = HydraObject & {
    id: string;
    target: Target;
    data: Record<string, unknown>;
    createdAt?: string;
};

/** Asset created by `POST /assets` once a file is fully uploaded */
export type UploadedAsset = {
    id: string;
};

/**
 * Field errors answered by `POST /form/validate` and `POST /downloads`: the
 * property path (`_form` for the whole form) => its messages.
 */
export type FormErrors = Record<string, string[]>;

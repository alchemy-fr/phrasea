/**
 * Uploader API contracts (API Platform / Hydra).
 * These types mirror the JSON payloads returned by `uploader/api`.
 */

import type {LiFormSchema} from '@/features/liform/types';
import type {FormDefinition} from '@/features/form-builder/types';

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

/** Matches `FormSchema::FORMAT_*` of the API */
export type FormSchemaFormat = 'liform' | 'builder';

type FormSchemaBase = HydraObject & {
    id: string;
    target: Target;
    locale?: string | null;
    localeMode: LocaleMode;
};

export type LiFormFormSchema = FormSchemaBase & {
    format: 'liform';
    data: LiFormSchema;
};

export type BuilderFormSchema = FormSchemaBase & {
    format: 'builder';
    data: FormDefinition;
};

export type FormSchema = LiFormFormSchema | BuilderFormSchema;

export function isBuilderSchema(
    schema: FormSchema | null | undefined
): schema is BuilderFormSchema {
    return schema?.format === 'builder';
}

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

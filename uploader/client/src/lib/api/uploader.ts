import {api, isApiError} from './http';
import {fetchAllPages, toPage} from './hydra';
import type {
    FormErrors,
    FormSchema,
    HydraCollection,
    LocaleMode,
    Page,
    Target,
    TargetParams,
    UploadedAsset,
    UploaderUser,
} from '@/types/api';
import type {LiFormSchema} from '@/features/liform/types';
import type {
    DataboxField,
    DataboxWorkspace,
    FieldOptionsPage,
    FieldOptionsQuery,
    FormDefinition,
} from '@/features/form-builder/types';
import type {MultipartUpload} from './upload';

export function iri(resource: 'targets' | 'form-schemas', id: string): string {
    return `/${resource}/${id}`;
}

/**
 * `POST /form/validate` and `POST /downloads` answer 200 with `{errors}` on
 * an invalid form, PHP encoding "no error" as an empty array.
 */
export function normalizeFormErrors(errors: unknown): FormErrors {
    if (!errors || typeof errors !== 'object' || Array.isArray(errors)) {
        return {};
    }
    const result: FormErrors = {};
    for (const [field, messages] of Object.entries(errors)) {
        const list = (Array.isArray(messages) ? messages : [messages])
            .filter(m => m !== null && m !== undefined && m !== '')
            .map(String);
        if (list.length > 0) {
            result[field] = list;
        }
    }

    return result;
}

export function hasFormErrors(errors: FormErrors): boolean {
    return Object.keys(errors).length > 0;
}

// -- User ----------------------------------------------------------------

export function getMe(): Promise<UploaderUser> {
    return api.get<UploaderUser>('/me');
}

// -- Targets -------------------------------------------------------------

export async function getTargetsPage(
    params: {query?: string; page?: number} = {},
    nextUrl?: string
): Promise<Page<Target>> {
    return toPage(
        await api.get<HydraCollection<Target>>(nextUrl ?? '/targets', {
            params: nextUrl ? undefined : params,
        })
    );
}

/** Every target the user may upload to */
export function getAllTargets(): Promise<Target[]> {
    return fetchAllPages(getTargetsPage(), next =>
        getTargetsPage(undefined, next)
    );
}

export function getTarget(id: string): Promise<Target> {
    return api.get<Target>(`/targets/${id}`);
}

/**
 * The form schema of the target for the current UI language. Null when the
 * target has none (the API answers an empty body, or 404 on older versions).
 */
export async function getTargetFormSchema(
    targetId: string
): Promise<FormSchema | null> {
    try {
        const schema = await api.get<FormSchema | null | ''>(
            `/targets/${targetId}/form-schema`
        );

        if (!schema || typeof schema !== 'object' || !schema.data) {
            return null;
        }

        // Schemas saved before the builder existed have no format
        return {...schema, format: schema.format ?? 'liform'} as FormSchema;
    } catch (e) {
        if (isApiError(e, 404)) {
            return null;
        }
        throw e;
    }
}

// -- Upload flow ---------------------------------------------------------

export async function validateForm(
    targetId: string,
    data: Record<string, unknown>
): Promise<FormErrors> {
    const res = await api.post<{errors?: unknown}>('/form/validate', {
        target: iri('targets', targetId),
        data,
    });

    return normalizeFormErrors(res?.errors);
}

/** Completes the multipart upload of a file: the asset of the batch */
export function createAsset(
    targetId: string,
    multipart: MultipartUpload,
    signal?: AbortSignal
): Promise<UploadedAsset> {
    return api.post<UploadedAsset>('/assets', {targetId, multipart}, {signal});
}

export async function commit(input: {
    targetId: string;
    files: string[];
    formData: Record<string, unknown>;
    schemaId?: string;
}): Promise<void> {
    await api.post('/commit', {
        target: iri('targets', input.targetId),
        files: input.files,
        formData: input.formData,
        schemaId: input.schemaId,
    });
}

/**
 * Asks the server to download `url` into the target. Resolves to the form
 * errors, empty when the download is scheduled.
 */
export async function downloadUrl(input: {
    targetId: string;
    url: string;
    formData: Record<string, unknown>;
    schemaId?: string;
}): Promise<FormErrors> {
    const res = await api.post<unknown>('/downloads', {
        target: iri('targets', input.targetId),
        url: input.url,
        formData: input.formData,
        schemaId: input.schemaId,
    });

    return normalizeFormErrors(
        res && typeof res === 'object'
            ? (res as {errors?: unknown}).errors
            : undefined
    );
}

// -- Admin: form schemas -------------------------------------------------

export type FormSchemaInput = {
    target: string;
    locale: string | null;
    localeMode: LocaleMode;
} & (
    | {format: 'liform'; data: LiFormSchema}
    | {format: 'builder'; data: FormDefinition}
);

export async function getFormSchemas(): Promise<FormSchema[]> {
    const first = api
        .get<HydraCollection<FormSchema>>('/form-schemas')
        .then(r => toPage(r));

    return fetchAllPages(first, next =>
        api.get<HydraCollection<FormSchema>>(next).then(r => toPage(r))
    );
}

export function getFormSchema(id: string): Promise<FormSchema> {
    return api.get<FormSchema>(`/form-schemas/${id}`);
}

export function saveFormSchema(
    id: string | undefined,
    input: FormSchemaInput
): Promise<FormSchema> {
    return id
        ? api.patch<FormSchema>(`/form-schemas/${id}`, input)
        : api.post<FormSchema>('/form-schemas', input);
}

export function deleteFormSchema(id: string): Promise<void> {
    return api.delete(`/form-schemas/${id}`);
}

// -- Databox, read through the uploader API ------------------------------

function optionsParams(query: FieldOptionsQuery) {
    return {
        query: query.query || undefined,
        ids: query.ids?.length ? query.ids : undefined,
        page: query.page && query.page > 1 ? query.page : undefined,
    };
}

/** The values of an entity or tag field of the target's form */
export function getFormFieldOptions(
    targetId: string,
    key: string,
    query: FieldOptionsQuery
): Promise<FieldOptionsPage> {
    return api.get<FieldOptionsPage>(
        `/targets/${targetId}/form-fields/${encodeURIComponent(key)}/options`,
        {params: optionsParams(query)}
    );
}

export function getDataboxWorkspaces(): Promise<DataboxWorkspace[]> {
    return api.get<DataboxWorkspace[]>('/databox/workspaces');
}

export function getDataboxWorkspace(id: string): Promise<DataboxWorkspace> {
    return api.get<DataboxWorkspace>(`/databox/workspaces/${id}`);
}

/** The attributes of the workspace an uploader can fill */
export function getDataboxFields(workspaceId: string): Promise<DataboxField[]> {
    return api.get<DataboxField[]>(`/databox/workspaces/${workspaceId}/fields`);
}

export function getDataboxEntityValues(
    entityListId: string,
    query: FieldOptionsQuery
): Promise<FieldOptionsPage> {
    return api.get<FieldOptionsPage>(
        `/databox/entity-lists/${entityListId}/values`,
        {params: optionsParams(query)}
    );
}

export function getDataboxTags(
    workspaceId: string,
    query: FieldOptionsQuery
): Promise<FieldOptionsPage> {
    return api.get<FieldOptionsPage>(
        `/databox/workspaces/${workspaceId}/tags`,
        {
            params: optionsParams(query),
        }
    );
}

/** The Databox collection the target uploads to by default, if any */
export async function getTargetDestination(
    targetId: string
): Promise<{id: string; title: string; workspaceId: string | null} | null> {
    const res = await api.get<{
        collection: {
            id: string;
            title: string;
            workspaceId: string | null;
        } | null;
    }>(`/databox/targets/${targetId}/destination`);

    return res.collection;
}

// -- Admin: target params ------------------------------------------------

export type TargetParamsInput = {
    target: string;
    data: Record<string, unknown>;
};

export async function getTargetParamsList(): Promise<TargetParams[]> {
    const first = api
        .get<HydraCollection<TargetParams>>('/target-params')
        .then(r => toPage(r));

    return fetchAllPages(first, next =>
        api.get<HydraCollection<TargetParams>>(next).then(r => toPage(r))
    );
}

export function getTargetParams(id: string): Promise<TargetParams> {
    return api.get<TargetParams>(`/target-params/${id}`);
}

export function saveTargetParams(
    id: string | undefined,
    input: TargetParamsInput
): Promise<TargetParams> {
    return id
        ? api.patch<TargetParams>(`/target-params/${id}`, input)
        : api.post<TargetParams>('/target-params', input);
}

export function deleteTargetParams(id: string): Promise<void> {
    return api.delete(`/target-params/${id}`);
}

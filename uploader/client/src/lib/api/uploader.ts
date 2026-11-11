import {api} from './http';
import {fetchAllPages, toPage} from './hydra';
import type {
    FormErrors,
    HydraCollection,
    Page,
    Target,
    TargetData,
    UploadedAsset,
    UploaderUser,
} from '@/types/api';
import type {
    DataboxField,
    DataboxWorkspace,
    FieldOptionsPage,
    FieldOptionsQuery,
    FormDefinition,
} from '@/features/form-builder/types';
import type {MultipartUpload} from './upload';

export function iri(resource: 'targets', id: string): string {
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

/** The form of the target, null when it has none */
export async function getTargetForm(
    targetId: string
): Promise<FormDefinition | null> {
    return nullIfEmpty(
        await api.get<FormDefinition | null | undefined>(
            `/targets/${targetId}/form`
        )
    );
}

/** Replaces the form of the target; null removes it */
export async function saveTargetForm(
    targetId: string,
    form: FormDefinition | null
): Promise<FormDefinition | null> {
    return nullIfEmpty(
        await api.put<FormDefinition | null | undefined>(
            `/targets/${targetId}/form`,
            {form}
        )
    );
}

/**
 * The fixed data merged, on commit, into the form data of every batch sent
 * to the target; null when it has none.
 */
export async function getTargetData(
    targetId: string
): Promise<TargetData | null> {
    return nullIfEmpty(
        await api.get<TargetData | null | undefined>(
            `/targets/${targetId}/data`
        )
    );
}

/** Replaces the data of the target; null (or an empty object) removes them */
export async function saveTargetData(
    targetId: string,
    data: TargetData | null
): Promise<TargetData | null> {
    return nullIfEmpty(
        await api.put<TargetData | null | undefined>(
            `/targets/${targetId}/data`,
            {data}
        )
    );
}

function nullIfEmpty<T extends object>(value: T | null | undefined): T | null {
    return value && typeof value === 'object' ? value : null;
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

/** Withdraws a file sent to a batch not committed yet */
export function deleteAsset(id: string): Promise<void> {
    return api.delete(`/assets/${id}`);
}

export async function commit(input: {
    targetId: string;
    files: string[];
    formData: Record<string, unknown>;
}): Promise<void> {
    await api.post('/commit', {
        target: iri('targets', input.targetId),
        files: input.files,
        formData: input.formData,
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
}): Promise<FormErrors> {
    const res = await api.post<unknown>('/downloads', {
        target: iri('targets', input.targetId),
        url: input.url,
        formData: input.formData,
    });

    return normalizeFormErrors(
        res && typeof res === 'object'
            ? (res as {errors?: unknown}).errors
            : undefined
    );
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

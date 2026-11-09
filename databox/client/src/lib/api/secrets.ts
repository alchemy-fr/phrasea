import {api} from './http';
import {toPage} from './hydra';
import {
    EntityName,
    HydraCollection,
    Page,
    WorkspaceEnv,
    WorkspaceSecret,
} from '@/types/api';
import {iri} from '@/lib/utils/iri';

/**
 * Where secrets and variables live: a workspace, or the whole instance
 * (administrators only) without one.
 */
export type SecretScope = {workspaceId?: string};

/** Query key prefix of every listing of secrets */
export const secretsQueryKey = 'workspace-secrets';
/** Query key prefix of every listing of variables */
export const envsQueryKey = 'workspace-envs';

function scopeParams({workspaceId}: SecretScope): Record<string, unknown> {
    return workspaceId ? {workspace: workspaceId} : {global: true};
}

/** The secrets of a workspace (to its editors) or of the instance, by name */
export async function getWorkspaceSecrets(
    scope: SecretScope,
    signal?: AbortSignal
): Promise<Page<WorkspaceSecret>> {
    return toPage(
        await api.get<HydraCollection<WorkspaceSecret>>(
            `/${EntityName.WorkspaceSecret}`,
            {params: {...scopeParams(scope), limit: 100}, signal}
        )
    );
}

/** The variables (names only), managed in the admin */
export async function getWorkspaceEnvs(
    scope: SecretScope,
    signal?: AbortSignal
): Promise<Page<WorkspaceEnv>> {
    return toPage(
        await api.get<HydraCollection<WorkspaceEnv>>(
            `/${EntityName.WorkspaceEnv}`,
            {params: {...scopeParams(scope), limit: 100}, signal}
        )
    );
}

export function postWorkspaceSecret({
    workspaceId,
    name,
    value,
}: SecretScope & {name: string; value: string}): Promise<WorkspaceSecret> {
    return api.post<WorkspaceSecret>(`/${EntityName.WorkspaceSecret}`, {
        workspace: workspaceId ? iri(EntityName.Workspace, workspaceId) : null,
        name,
        value,
    });
}

/** Replaces the value; the name of a secret cannot change */
export function putWorkspaceSecret(
    id: string,
    data: {value: string}
): Promise<WorkspaceSecret> {
    return api.patch<WorkspaceSecret>(
        `/${EntityName.WorkspaceSecret}/${id}`,
        data
    );
}

export function deleteWorkspaceSecret(id: string): Promise<void> {
    return api.delete(`/${EntityName.WorkspaceSecret}/${id}`);
}

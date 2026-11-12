'use client';

import {useQuery} from '@tanstack/react-query';
import {isApiError} from '@/lib/api/http';
import {
    envsQueryKey,
    getSystemEnvs,
    getWorkspaceEnvs,
    getWorkspaceSecrets,
    SecretScope,
    secretsQueryKey,
    systemEnvsQueryKey,
} from '@/lib/api/secrets';

const retry = (failureCount: number, error: unknown) =>
    !isApiError(error) && failureCount < 2;

export function secretScopeKey({workspaceId}: SecretScope): string {
    return workspaceId ?? 'global';
}

/**
 * The secrets and variables a reference of the scope can point to. Listing
 * them needs to edit the workspace (to be an administrator for the instance):
 * `forbidden` then, which is not an error to report.
 */
export function useSecretNames(scope: SecretScope, enabled = true) {
    const key = secretScopeKey(scope);
    const secrets = useQuery({
        queryKey: [secretsQueryKey, key],
        queryFn: ({signal}) => getWorkspaceSecrets(scope, signal),
        enabled,
        retry,
        staleTime: 30_000,
    });
    const envs = useQuery({
        queryKey: [envsQueryKey, key],
        queryFn: ({signal}) => getWorkspaceEnvs(scope, signal),
        enabled,
        retry,
        staleTime: 30_000,
    });

    return {
        secrets: secrets.data?.items,
        envs: envs.data?.items,
        loading: secrets.isLoading || envs.isLoading,
        forbidden:
            isApiError(secrets.error, 403) || isApiError(envs.error, 403),
        error: secrets.error ?? envs.error,
    };
}

/**
 * The names of the system variables of the instance a reference of the scope
 * can point to, resolved after its secrets and variables. `names` stays
 * undefined on an error (a 403 included): nothing to list nor to report.
 */
export function useSystemEnvNames(scope: SecretScope, enabled = true) {
    const query = useQuery({
        queryKey: [systemEnvsQueryKey, secretScopeKey(scope)],
        queryFn: ({signal}) => getSystemEnvs(scope, signal),
        enabled,
        retry,
        staleTime: 30_000,
    });

    return {
        names: query.data,
        loading: query.isLoading,
    };
}

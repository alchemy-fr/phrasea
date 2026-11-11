import {
    createGroupsApi,
    type GroupsSearchParams,
} from '@alchemy/next-ui/groups/createGroupsApi';
import type {GroupsApi} from '@alchemy/next-ui/groups/types';
import {ApiError} from '@alchemy/next-ui/utils/errors';
import {getAuthClient} from '@/lib/auth/client';

type RequestOptions = {
    params?: GroupsSearchParams;
    json?: unknown;
    signal?: AbortSignal;
};

/**
 * The management of the Keycloak groups through the Databox API (the
 * dashboard has no API of its own), with the token of the user.
 */
export function createDataboxGroupsApi(apiUrl: string): GroupsApi {
    const request = async <T>(
        method: string,
        path: string,
        {params, json, signal}: RequestOptions = {}
    ): Promise<T> => {
        const url = new URL(`${apiUrl.replace(/\/+$/, '')}${path}`);
        Object.entries(params ?? {}).forEach(([key, value]) => {
            if (value !== undefined) {
                url.searchParams.set(key, String(value));
            }
        });
        const token = await getAuthClient()
            .getAccessToken()
            .catch(() => undefined);
        const headers: Record<string, string> = {Accept: 'application/json'};
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }
        if (json !== undefined) {
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch(url, {
            method,
            headers,
            signal,
            body: json !== undefined ? JSON.stringify(json) : undefined,
        });
        const text = await response.text();
        let data: unknown = text || undefined;
        try {
            data = text ? JSON.parse(text) : undefined;
        } catch {
            // not JSON: the text itself
        }

        if (!response.ok) {
            const body =
                data && typeof data === 'object'
                    ? (data as Record<string, unknown>)
                    : undefined;
            const message =
                [body?.detail, body?.message, body?.error_description, data]
                    .filter(
                        (v): v is string => typeof v === 'string' && v !== ''
                    )
                    .at(0) ?? `${response.status} ${response.statusText}`;

            throw new ApiError(message, response.status, data, response);
        }

        return data as T;
    };

    return createGroupsApi({
        get: (path, params, signal) => request('GET', path, {params, signal}),
        post: (path, json) => request('POST', path, {json}),
        put: (path, json) => request('PUT', path, {json: json ?? {}}),
        delete: path => request('DELETE', path),
    });
}

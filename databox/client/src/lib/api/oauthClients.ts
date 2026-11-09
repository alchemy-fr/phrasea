import {api} from './http';
import {toPage} from './hydra';
import {HydraCollection, OAuthClient} from '@/types/api';

/**
 * The Keycloak OAuth clients an integration can authorize its users with,
 * the ones allowing its redirect URI first. Read with the token of the user:
 * it needs the `view-clients` role of `realm-management`.
 */
export async function getOAuthClients(
    {
        search,
        integrationId,
    }: {
        search?: string;
        /** Without it, the redirect URI checked is the one of any integration */
        integrationId?: string;
    },
    signal?: AbortSignal
): Promise<OAuthClient[]> {
    return toPage(
        await api.get<HydraCollection<OAuthClient>>('/oauth-clients', {
            params: {
                search: search || undefined,
                integrationId,
            },
            signal,
        })
    ).items;
}

/**
 * Creates a public client with the standard flow, allowing the redirect URI
 * of every integration. Needs the `manage-clients` role of `realm-management`.
 */
export async function createOAuthClient(data: {
    clientId: string;
    name?: string;
    description?: string;
}): Promise<OAuthClient> {
    return api.post<OAuthClient>('/oauth-clients', data);
}

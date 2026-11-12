import {api} from './http';

/**
 * Expose, through the proxies of an Expose integration: the requests are sent
 * with the Expose access of the current user.
 */

export type ExposePublication = {
    id: string;
    title: string;
    slug?: string | null;
};

export type ExposeProfile = {
    id: string;
    name: string;
};

export type ExposePublicationInput = {
    title: string;
    slug?: string;
    description?: string;
    enabled?: boolean;
    /** ID of the parent publication */
    parent?: string;
    /** ID of the publication profile */
    profile?: string;
};

type ExposeCollection<T> = {
    'member'?: T[];
    'hydra:member'?: T[];
};

function members<T>(response: ExposeCollection<T>): T[] {
    return response.member ?? response['hydra:member'] ?? [];
}

/**
 * The publications the current user can edit (first page only, narrow it with the title).
 */
export async function getExposePublications(
    integrationId: string,
    {title}: {title?: string} = {}
): Promise<ExposePublication[]> {
    return members(
        await api.get<ExposeCollection<ExposePublication>>(
            `/integrations/expose/${integrationId}/proxy/publications`,
            {
                params: {
                    'editable': true,
                    'flatten': true,
                    'title': title || undefined,
                    'order[title]': 'asc',
                },
            }
        )
    );
}

export async function getExposeProfiles(
    integrationId: string
): Promise<ExposeProfile[]> {
    return members(
        await api.get<ExposeCollection<ExposeProfile>>(
            `/integrations/expose/${integrationId}/proxy/profiles`
        )
    );
}

/**
 * Creates a publication, owned by the current user in Expose.
 */
export function createExposePublication(
    integrationId: string,
    data: ExposePublicationInput
): Promise<{id: string; title: string}> {
    return api.post<{id: string; title: string}>(
        `/integrations/expose/${integrationId}/publications`,
        data
    );
}

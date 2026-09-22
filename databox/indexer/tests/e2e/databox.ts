/**
 * The databox API, as the suite sees it.
 *
 * Everything is read and written through the API; the suite never connects to
 * the database. `GET /collections` and `GET /assets` are served from
 * Elasticsearch, which is fed from the writes the indexer just made, hence
 * `waitFor`.
 */

import {API_URL, CLIENT_ID, CLIENT_SECRET, SLUG} from './env';

export type Collection = {
    id: string;
    name: string;
};

export type AlternateUrl = {
    type: string;
    url: string;
};

export type Asset = {
    id: string;
    referenceCollection?: Collection;
    collections: Collection[];
    source?: {
        id: string;
        alternateUrls?: AlternateUrl[];
    };
};

export type Workspace = {
    id: string;
    slug: string;
};

/** Every collection of the workspace, as `/e2e/level1` style paths of names. */
export type CollectionTree = {
    paths: string[];
    pathOf: Record<string, string>;
};

let token: string | undefined;

export async function authenticate(): Promise<string> {
    if (token) {
        return token;
    }

    const url = `${API_URL}/oauth/v2/token`;
    let res: Response;

    try {
        res = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                grant_type: 'client_credentials',
                client_id: CLIENT_ID,
                client_secret: CLIENT_SECRET,
                scope: 'admin',
            }),
        });
    } catch (e) {
        // fetch() reports every transport failure as "fetch failed"; the cause
        // is what says whether the name did not resolve or nothing answered.
        const cause = (e as {cause?: Error}).cause;

        throw new Error(
            `${url} is unreachable: ${cause?.message ?? (e as Error).message}\n` +
                '  The databox API must be up and reachable from the indexer container:\n' +
                '    docker compose up -d databox-api-php databox-api-nginx',
            {cause: e}
        );
    }

    if (!res.ok) {
        throw new Error(
            `could not authenticate as "${CLIENT_ID}" (HTTP ${res.status}): ` +
                `${(await res.text()).slice(0, 300)}\n` +
                '  The Keycloak client is created by bin/setup.sh; run it if you never did.'
        );
    }

    token = ((await res.json()) as {access_token: string}).access_token;

    return token;
}

/** The `sub` claim of the access token, which is the indexer's owner id. */
export async function getOwnerId(): Promise<string> {
    const claims = JSON.parse(
        Buffer.from(
            (await authenticate()).split('.')[1],
            'base64url'
        ).toString()
    ) as {sub?: string};

    if (!claims.sub) {
        throw new Error('the access token carries no "sub" claim');
    }

    return claims.sub;
}

async function request(
    path: string,
    init: RequestInit = {}
): Promise<Response> {
    return fetch(`${API_URL}${path}`, {
        ...init,
        headers: {
            Authorization: `Bearer ${await authenticate()}`,
            Accept: 'application/ld+json',
            ...(init.headers ?? {}),
        },
    });
}

async function get<T>(path: string): Promise<T> {
    const res = await request(path);

    if (!res.ok) {
        throw new Error(`GET ${path} failed (HTTP ${res.status})`);
    }

    return (await res.json()) as T;
}

async function members<T>(path: string): Promise<T[]> {
    const body = await get<{'hydra:member'?: T[]}>(path);

    return body['hydra:member'] ?? [];
}

/**
 * Re-runs `produce` until `isReady` accepts its result, or until the deadline.
 * The last result is returned either way, so the assertion that follows is
 * what reports the failure.
 */
export async function waitFor<T>(
    produce: () => Promise<T>,
    isReady: (value: T) => boolean,
    {attempts = 30, delay = 1000}: {attempts?: number; delay?: number} = {}
): Promise<T> {
    let value = await produce();

    for (let i = 1; i < attempts && !isReady(value); i++) {
        await new Promise(resolve => setTimeout(resolve, delay));
        value = await produce();
    }

    return value;
}

export async function findWorkspace(): Promise<Workspace | null> {
    const res = await request(`/workspaces-by-slug/${SLUG}`);

    if (404 === res.status) {
        return null;
    }
    if (!res.ok) {
        throw new Error(
            `looking up workspace "${SLUG}" failed (HTTP ${res.status})`
        );
    }

    return (await res.json()) as Workspace;
}

/**
 * DELETE only soft-deletes, but the SoftDeleteListener dispatches a
 * DeleteWorkspace message that empties the row for real — synchronously in
 * APP_ENV=dev, through a consumer otherwise. The unique index on
 * workspace.slug does not filter deleted_at, so a leftover blocks the next run.
 */
export async function deleteWorkspace(id: string): Promise<void> {
    const res = await request(`/workspaces/${id}`, {method: 'DELETE'});

    if (!res.ok && 404 !== res.status) {
        throw new Error(`deleting workspace ${id} failed (HTTP ${res.status})`);
    }
}

/**
 * Walks the collection tree of the workspace. The listing nests only one level
 * of children, so each level is fetched through the `parent` filter.
 */
export async function getCollectionTree(
    workspaceId: string
): Promise<CollectionTree> {
    const tree: CollectionTree = {paths: [], pathOf: {}};

    const walk = async (
        parentId: string | null,
        parentPath: string
    ): Promise<void> => {
        const path = parentId
            ? `/collections?parent=${parentId}&limit=100`
            : `/collections?workspaces[]=${workspaceId}&limit=100`;

        for (const collection of await members<Collection>(path)) {
            const childPath = `${parentPath}/${collection.name}`;
            tree.paths.push(childPath);
            tree.pathOf[collection.id] = childPath;

            await walk(collection.id, childPath);
        }
    };

    await walk(null, '');

    return tree;
}

export async function getAssets(
    workspaceId: string,
    {includeDeleted = false}: {includeDeleted?: boolean} = {}
): Promise<Asset[]> {
    return members<Asset>(
        `/assets?workspaces[]=${workspaceId}&limit=100` +
            (includeDeleted ? '&include_deleted=true' : '')
    );
}

/** The `indexer://` alternate URL of an asset's source file. */
export function sourceUrlOf(asset: Asset): string {
    const alternate = (asset.source?.alternateUrls ?? []).find(
        u => 'indexer' === u.type
    );

    return alternate ? alternate.url : '<none>';
}

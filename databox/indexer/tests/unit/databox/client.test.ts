import axios, {AxiosInstance} from 'axios';
import AxiosMockAdapter from 'axios-mock-adapter';
import {createTestLogger} from '../../helpers/logger';
import {clearPromiseLocks} from '../../../src/lib/promise';

const holder = vi.hoisted(() => ({
    instance: undefined as unknown as AxiosInstance,
    createHttpClientArgs: undefined as any,
    oauthArgs: undefined as any,
    tokenCalls: 0,
}));

// The real client wraps axios with axios-retry (10 attempts, 1s backoff) and
// verbose interceptors; the suite targets the request shapes the client emits,
// so it is handed a bare axios instance instead.
vi.mock('../../../src/lib/axios', () => ({
    createHttpClient: (args: any) => {
        holder.createHttpClientArgs = args;

        return holder.instance;
    },
}));

vi.mock('@alchemy/auth', () => ({
    OAuthClient: class {
        constructor(args: any) {
            holder.oauthArgs = args;
        }
        async getTokenFromClientCredentials() {
            holder.tokenCalls++;

            return {access_token: 'token'};
        }
    },
    configureClientAuthentication: vi.fn(),
    configureClientCredentials401Retry: vi.fn(),
    GrantTypeRefreshMethod: {
        clientCredentials: 'getTokenFromClientCredentials',
        refreshToken: 'refreshToken',
    },
}));

vi.mock('@alchemy/storage', () => ({
    MemoryStorage: class {},
}));

const {DataboxClient, clearCollectionKeyCache, createDataboxClientFromConfig} =
    await import('../../../src/databox/client');

let mock: AxiosMockAdapter;
let client: InstanceType<typeof DataboxClient>;
const logger = createTestLogger();

beforeEach(() => {
    holder.instance = axios.create({baseURL: 'http://databox-api.test'});
    mock = new AxiosMockAdapter(holder.instance, {onNoMatch: 'throwException'});
    clearCollectionKeyCache();
    clearPromiseLocks();
    logger.lines.length = 0;

    client = new DataboxClient(
        {
            apiUrl: 'http://databox-api.test',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            ownerId: 'owner-1',
            verifySSL: false,
            scope: 'admin',
        },
        logger
    );
});

const lastBody = () => JSON.parse(mock.history.post.at(-1)!.data);

describe('construction', () => {
    it('points the OAuth client at /oauth/v2 and keeps the scope', () => {
        expect(holder.oauthArgs).toMatchObject({
            clientId: 'client-id',
            clientSecret: 'client-secret',
            scope: 'admin',
            baseUrl: 'http://databox-api.test/oauth/v2',
        });
    });

    it('asks for ld+json and forwards verifySSL', () => {
        expect(holder.createHttpClientArgs).toMatchObject({
            baseURL: 'http://databox-api.test',
            verifySSL: false,
            headers: {Accept: 'application/ld+json'},
        });
    });

    it('builds from the config file', () => {
        const fromConfig = createDataboxClientFromConfig(logger);

        expect(fromConfig).toBeInstanceOf(DataboxClient);
        expect(holder.oauthArgs).toMatchObject({
            clientId: 'test-client',
            clientSecret: 'test-secret',
        });
    });
});

describe('authenticate', () => {
    it('delegates to the OAuth client credentials grant', async () => {
        const before = holder.tokenCalls;

        await client.authenticate();

        expect(holder.tokenCalls).toEqual(before + 1);
    });
});

describe('createAsset', () => {
    beforeEach(() => {
        mock.onPost('/assets').reply(200, {id: 'asset-1'});
    });

    it('turns workspaceId into an IRI and injects the owner', async () => {
        const out = await client.createAsset({
            workspaceId: 'ws-1',
            key: 'a/b.jpg',
            name: 'b.jpg',
        });

        expect(out).toEqual({id: 'asset-1'});
        expect(lastBody()).toEqual({
            ownerId: 'owner-1',
            workspace: '/workspaces/ws-1',
            key: 'a/b.jpg',
            name: 'b.jpg',
        });
        expect(lastBody()).not.toHaveProperty('workspaceId');
    });

    it('keeps an explicit workspace IRI untouched', async () => {
        await client.createAsset({
            workspace: '/workspaces/ws-2',
            key: 'a/b.jpg',
        });

        expect(lastBody().workspace).toEqual('/workspaces/ws-2');
    });

    it('leaves a name of exactly 255 characters alone', async () => {
        const name = 'x'.repeat(255);

        await client.createAsset({workspaceId: 'ws-1', name});

        expect(lastBody().name).toEqual(name);
        expect(logger.messages('warn')).toEqual([]);
    });

    it('truncates a longer name to 255 characters and warns', async () => {
        await client.createAsset({workspaceId: 'ws-1', name: 'x'.repeat(300)});

        const name = lastBody().name;
        expect(name).toHaveLength(255);
        expect(name).toEqual('x'.repeat(239) + ' ... [truncated]');
        expect(logger.messages('warn')).toHaveLength(1);
    });

    it('forwards attributes, tags, renditions and the story flag', async () => {
        await client.createAsset({
            workspaceId: 'ws-1',
            attributes: [{definition: '/d/1', value: 'v'} as any],
            tags: ['/tags/1'],
            renditions: [{name: 'preview'}],
            isStory: true,
            generateRenditions: false,
        });

        expect(lastBody()).toMatchObject({
            attributes: [{definition: '/d/1', value: 'v'}],
            tags: ['/tags/1'],
            renditions: [{name: 'preview'}],
            isStory: true,
            generateRenditions: false,
        });
    });
});

describe('copyAsset', () => {
    it('posts the copy payload with the owner', async () => {
        mock.onPost('/assets/copy').reply(200, {});

        await client.copyAsset({
            destination: '/collections/c1',
            ids: ['asset-1'],
            byReference: true,
            withAttributes: false,
            withTags: false,
        });

        expect(lastBody()).toEqual({
            ownerId: 'owner-1',
            destination: '/collections/c1',
            ids: ['asset-1'],
            byReference: true,
            withAttributes: false,
            withTags: false,
        });
    });
});

describe('deleteAsset', () => {
    it('sends workspaceId and the key in the DELETE body', async () => {
        mock.onDelete('/assets-by-keys').reply(200, {});

        await client.deleteAsset('ws-1', 'a/b.jpg');

        expect(JSON.parse(mock.history.delete[0].data)).toEqual({
            workspaceId: 'ws-1',
            keys: ['a/b.jpg'],
        });
    });
});

describe('createCollection', () => {
    it('posts once and returns the id', async () => {
        mock.onPost('/collections').reply(200, {id: 'coll-1'});

        expect(
            await client.createCollection('/a', {
                workspaceId: 'ws-1',
                key: '/a',
                name: 'a',
            })
        ).toEqual('coll-1');

        expect(mock.history.post).toHaveLength(1);
        expect(lastBody()).toEqual({
            ownerId: 'owner-1',
            workspace: '/workspaces/ws-1',
            key: '/a',
            name: 'a',
        });
    });

    it('serves the second call from the cache without a request', async () => {
        mock.onPost('/collections').reply(200, {id: 'coll-1'});

        await client.createCollection('/a', {workspaceId: 'ws-1', key: '/a'});
        const second = await client.createCollection('/a', {
            workspaceId: 'ws-1',
            key: '/a',
        });

        expect(second).toEqual('coll-1');
        expect(mock.history.post).toHaveLength(1);
    });

    it('collapses concurrent calls for the same key into one request', async () => {
        mock.onPost('/collections').reply(200, {id: 'coll-1'});

        const ids = await Promise.all([
            client.createCollection('/a', {workspaceId: 'ws-1', key: '/a'}),
            client.createCollection('/a', {workspaceId: 'ws-1', key: '/a'}),
            client.createCollection('/a', {workspaceId: 'ws-1', key: '/a'}),
        ]);

        expect(ids).toEqual(['coll-1', 'coll-1', 'coll-1']);
        expect(mock.history.post).toHaveLength(1);
    });

    it('throws when neither workspaceId nor workspace is given', async () => {
        await expect(
            client.createCollection('/a', {key: '/a'})
        ).rejects.toThrow('Error creating collection: missing workspace');
    });
});

describe('createCollectionTreeBranch', () => {
    it('creates one collection per segment, chaining parents', async () => {
        let n = 0;
        mock.onPost('/collections').reply(() => [200, {id: `coll-${++n}`}]);

        const id = await client.createCollectionTreeBranch('ws-1', '', [
            {key: 'a', name: 'a'},
            {key: 'b', name: 'b'},
            {key: 'c', name: 'c'},
        ]);

        expect(id).toEqual('coll-3');
        expect(mock.history.post.map(r => JSON.parse(r.data))).toEqual([
            {
                ownerId: 'owner-1',
                workspace: '/workspaces/ws-1',
                key: '/a',
                name: 'a',
                parent: undefined,
            },
            {
                ownerId: 'owner-1',
                workspace: '/workspaces/ws-1',
                key: '/a/b',
                name: 'b',
                parent: '/collections/coll-1',
            },
            {
                ownerId: 'owner-1',
                workspace: '/workspaces/ws-1',
                key: '/a/b/c',
                name: 'c',
                parent: '/collections/coll-2',
            },
        ]);
    });

    it('prefixes every key with the given keyPrefix', async () => {
        mock.onPost('/collections').reply(200, {id: 'coll-1'});

        await client.createCollectionTreeBranch('ws-1', 'prefix', [
            {key: 'a', name: 'a'},
        ]);

        expect(lastBody().key).toEqual('prefix/a');
    });

    it('returns an empty id for an empty branch', async () => {
        expect(await client.createCollectionTreeBranch('ws-1', '', [])).toEqual(
            ''
        );
        expect(mock.history.post).toHaveLength(0);
    });

    it('reuses cached ancestors across two branches', async () => {
        let n = 0;
        mock.onPost('/collections').reply(() => [200, {id: `coll-${++n}`}]);

        await client.createCollectionTreeBranch('ws-1', '', [
            {key: 'a', name: 'a'},
            {key: 'b', name: 'b'},
        ]);
        await client.createCollectionTreeBranch('ws-1', '', [
            {key: 'a', name: 'a'},
            {key: 'c', name: 'c'},
        ]);

        expect(mock.history.post.map(r => JSON.parse(r.data).key)).toEqual([
            '/a',
            '/a/b',
            '/a/c',
        ]);
    });
});

describe('idempotent sub-resource creation', () => {
    it('creates an attribute definition once per key', async () => {
        mock.onPost('/attribute-definitions').reply(200, {id: 'def-1'});

        await client.createAttributeDefinition('k', {name: 'Title'});
        await client.createAttributeDefinition('k', {name: 'Title'});

        expect(mock.history.post).toHaveLength(1);
    });

    it('creates a tag once per key', async () => {
        mock.onPost('/tags').reply(200, {id: 'tag-1'});

        await client.createTag('k', {name: 'Tag'});
        await client.createTag('k', {name: 'Tag'});

        expect(mock.history.post).toHaveLength(1);
    });

    it('creates an attribute policy once per key', async () => {
        mock.onPost('/attribute-policies').reply(200, {id: 'pol-1'});

        await client.createAttributePolicy('k', {name: 'Policy'});
        await client.createAttributePolicy('k', {name: 'Policy'});

        expect(mock.history.post).toHaveLength(1);
    });

    it('does not deduplicate rendition policies or definitions', async () => {
        mock.onPost('/rendition-policies').reply(200, {id: 'rp-1'});
        mock.onPost('/rendition-definitions').reply(200, {id: 'rd-1'});

        expect(await client.createRenditionPolicy({name: 'p'})).toEqual('rp-1');
        await client.createRenditionPolicy({name: 'p'});
        expect(await client.createRenditionDefinition({name: 'd'})).toEqual(
            'rd-1'
        );

        expect(mock.history.post).toHaveLength(3);
    });
});

describe('collection endpoints returning hydra collections', () => {
    it('unwraps hydra:member for tags', async () => {
        mock.onGet('/tags').reply(200, {
            'hydra:member': [{id: 't1'}, {id: 't2'}],
        });

        expect(await client.getTags('ws-1')).toEqual([{id: 't1'}, {id: 't2'}]);
        expect(mock.history.get[0].params).toEqual({workspaceId: 'ws-1'});
    });

    it('unwraps hydra:member for rendition policies', async () => {
        mock.onGet('/rendition-policies').reply(200, {
            'hydra:member': [{id: 'p1'}],
        });

        expect(await client.getRenditionPolicies('ws-1')).toEqual([{id: 'p1'}]);
    });
});

describe('workspaces', () => {
    it('resolves a workspace id from its slug', async () => {
        mock.onGet('/workspaces-by-slug/my-slug').reply(200, {id: 'ws-1'});

        expect(await client.getWorkspaceIdFromSlug('my-slug')).toEqual('ws-1');
    });

    it('creates a workspace with the owner of the client', async () => {
        mock.onPost('/workspaces').reply(200, {id: 'ws-1'});

        expect(
            await client.createWorkspace({slug: 'my-slug', locales: ['fr']})
        ).toEqual('ws-1');

        expect(lastBody()).toEqual({
            name: 'my-slug',
            slug: 'my-slug',
            enabledLocales: ['fr'],
            localeFallbacks: [],
            ownerId: 'owner-1',
        });
    });

    it('does not fall back to the ownerId of the config file', () => {
        // The fixture config has databox.ownerId = "owner-1"; a client built
        // with another owner used to create workspaces owned by that one.
        mock.onPost('/workspaces').reply(200, {id: 'ws-2'});

        const other = new DataboxClient(
            {
                apiUrl: 'http://databox-api.test',
                clientId: 'client-id',
                clientSecret: 'client-secret',
                ownerId: 'someone-else',
                verifySSL: false,
                scope: 'admin',
            },
            logger
        );

        return other.createWorkspace({slug: 'other-slug'}).then(() => {
            expect(lastBody().ownerId).toEqual('someone-else');
        });
    });

    it('defaults enabledLocales to an empty array', async () => {
        mock.onPost('/workspaces').reply(200, {id: 'ws-1'});

        await client.createWorkspace({slug: 'my-slug'});

        expect(lastBody().enabledLocales).toEqual([]);
    });

    it('flushes a workspace', async () => {
        mock.onPost('/workspaces/ws-1/flush').reply(200, {id: 'ws-2'});

        expect(await client.flushWorkspace('ws-1')).toEqual('ws-2');
    });
});

describe('initWorkspace', () => {
    it('reuses an existing workspace', async () => {
        mock.onGet('/workspaces-by-slug/my-slug').reply(200, {id: 'ws-1'});

        expect(await client.initWorkspace({slug: 'my-slug', logger})).toEqual(
            'ws-1'
        );
        expect(mock.history.post).toHaveLength(0);
    });

    it('flushes the existing workspace when asked to', async () => {
        mock.onGet('/workspaces-by-slug/my-slug').reply(200, {id: 'ws-1'});
        mock.onPost('/workspaces/ws-1/flush').reply(200, {id: 'ws-2'});

        expect(
            await client.initWorkspace({
                slug: 'my-slug',
                flushExisting: true,
                logger,
            })
        ).toEqual('ws-2');
        expect(logger.hasMessageMatching('Flushing databox workspace')).toBe(
            true
        );
    });

    it('creates the workspace when the slug is unknown', async () => {
        mock.onGet('/workspaces-by-slug/my-slug').reply(404);
        mock.onPost('/workspaces').reply(200, {id: 'ws-new'});

        expect(
            await client.initWorkspace({
                slug: 'my-slug',
                locales: ['en'],
                logger,
            })
        ).toEqual('ws-new');
        expect(logger.hasMessageMatching('Creating databox workspace')).toBe(
            true
        );
    });

    it('propagates any other HTTP error', async () => {
        mock.onGet('/workspaces-by-slug/my-slug').reply(500);

        await expect(
            client.initWorkspace({slug: 'my-slug', logger})
        ).rejects.toThrow();
        expect(mock.history.post).toHaveLength(0);
    });
});

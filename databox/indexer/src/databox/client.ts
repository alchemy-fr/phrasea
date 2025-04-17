import {AxiosInstance} from 'axios';
import {
    AssetCopyInput,
    AssetInput,
    AssetOutput,
    StoryAssetOutput,
    AttributeClass,
    AttributeDefinition,
    CollectionInput,
    RenditionClass,
    Tag,
} from './types';
import {lockPromise} from '../lib/promise';
import {getConfig, getStrict} from '../configLoader';
import {Logger} from 'winston';
import {createHttpClient} from '../lib/axios';
import {
    configureClientAuthentication,
    configureClientCredentials401Retry,
    GrantTypeRefreshMethod,
    KeycloakUserInfoResponse,
    OAuthClient,
} from '@alchemy/auth';
import {MemoryStorage} from '@alchemy/storage';

function createApiClient(
    baseURL: string,
    clientId: string,
    clientSecret: string,
    verifySSL: boolean,
    scope?: string
) {
    const oauthClient = new OAuthClient<KeycloakUserInfoResponse>({
        clientId,
        clientSecret,
        scope,
        baseUrl: `${baseURL}/oauth/v2`,
        storage: new MemoryStorage(),
    });

    const client = createHttpClient({
        baseURL,
        verifySSL,
        headers: {Accept: 'application/ld+json'},
    });

    configureClientAuthentication(
        client,
        oauthClient,
        GrantTypeRefreshMethod.clientCredentials
    );
    configureClientCredentials401Retry(client, oauthClient);

    return {
        client,
        oauthClient,
    };
}

type ClientParameters = {
    apiUrl: string;
    clientId: string;
    clientSecret: string;
    scope?: string;
    verifySSL: boolean;
    ownerId: string;
};

const maxTitleLength = 255;

const collectionKeyMap: Record<string, string> = {};

export class DataboxClient {
    private readonly client: AxiosInstance;
    private readonly oauthClient: OAuthClient<KeycloakUserInfoResponse>;
    private readonly logger: Logger;
    private readonly ownerId: string;

    constructor(
        {
            apiUrl,
            clientId,
            clientSecret,
            scope,
            ownerId,
            verifySSL = true,
        }: ClientParameters,
        logger: Logger
    ) {
        const {client, oauthClient} = createApiClient(
            apiUrl,
            clientId,
            clientSecret,
            verifySSL,
            scope
        );
        this.client = client;
        this.oauthClient = oauthClient;
        this.ownerId = ownerId;
        this.logger = logger;
    }

    async createAsset(data: AssetInput): Promise<AssetOutput | StoryAssetOutput> {
        if (data.workspaceId) {
            data.workspace = `/workspaces/${data.workspaceId}`;
            delete data.workspaceId;
        }

        if (data.title && data.title.length > maxTitleLength) {
            const dots = ` ... [truncated]`;
            data.title =
                data.title.substring(0, maxTitleLength - dots.length) + dots;
            this.logger.warn(
                `Title truncated for asset ${JSON.stringify(data)}`
            );
        }

        const a = await this.client.post(`/assets`, {
            ownerId: this.ownerId,
            ...data,
        });

        return a.data;
    }

    async createStoryAsset(data: AssetInput): Promise<StoryAssetOutput> {
        return this.createAsset(data) as any;
    }

    async copyAsset(data: AssetCopyInput): Promise<void> {
        await this.client.post(`/assets/copy`, {
            ownerId: this.ownerId,
            ...data,
        });
    }

    public async authenticate() {
        this.logger.debug(`Authenticating to Databox...`);
        const res = await this.oauthClient.getTokenFromClientCredentials();
        this.logger.info(`Authenticated to Databox!`);

        return res;
    }

    async deleteAsset(workspaceId: string, key: string): Promise<void> {
        await this.client.delete(`/assets-by-keys`, {
            data: {
                workspaceId,
                keys: [key],
            },
        });
    }

    async createCollection(
        key: string,
        data: CollectionInput
    ): Promise<string> {
        if (collectionKeyMap[key]) {
            return collectionKeyMap[key];
        }

        if (data.workspaceId) {
            data.workspace = `/workspaces/${data.workspaceId}`;
            delete data.workspaceId;
        } else if (!data.workspace) {
            throw new Error(`Error creating collection: missing workspace`);
        }

        const r = await lockPromise(key, async () => {
            return (
                await this.client.post(`/collections`, {
                    ownerId: this.ownerId,
                    ...data,
                })
            ).data;
        });

        return (collectionKeyMap[key] = r.id);
    }

    async createCollectionTreeBranch(
        workspaceId: string,
        keyPrefix: string,
        data: CollectionInput[],
    ): Promise<string> {
        let parentId: string | undefined = undefined;
        let key = keyPrefix;
        let id = '';
        for (let i = 0; i < data.length; ++i) {
            key += '/' + data[i].key;
            id = await this.createCollection(key, {
                ...data[i],
                workspaceId: workspaceId,
                key: key,
                parent: parentId,
            });
            parentId = `/collections/${id}`;
        }

        return id!;
    }

    async createAttributeDefinition(
        key: string,
        data: Partial<AttributeDefinition>
    ): Promise<AttributeDefinition> {
        return await lockPromise(`attr-def-${key}`, async () => {
            return (await this.client.post(`/attribute-definitions`, data))
                .data;
        });
    }

    async createTag(key: string, data: Partial<Tag>): Promise<Tag> {
        return await lockPromise(`tag-${key}`, async () => {
            return (await this.client.post(`/tags`, data)).data;
        });
    }

    async getTags(workspaceId: string): Promise<Tag[]> {
        const res = await this.client.get(`/tags`, {
            params: {
                workspaceId,
            },
        });

        return res.data['hydra:member'];
    }

    async createAttributeClass(
        key: string,
        data: Partial<AttributeClass>
    ): Promise<AttributeClass> {
        return await lockPromise(`attr-class-${key}`, async () => {
            return (await this.client.post(`/attribute-classes`, data)).data;
        });
    }

    async createRenditionClass(data: object): Promise<string> {
        const res = await this.client.post(`/rendition-classes`, data);

        return res.data.id;
    }

    async getRenditionClasses(workspaceId: string): Promise<RenditionClass[]> {
        const res = await this.client.get(`/rendition-classes`, {
            params: {
                workspaceId,
            },
        });

        return res.data['hydra:member'];
    }

    async createRenditionDefinition(data: object): Promise<string> {
        const res = await this.client.post(`/rendition-definitions`, data);
        return res.data.id;
    }

    async flushWorkspace(workspaceId: string): Promise<string> {
        const res = await this.client.post(
            `/workspaces/${workspaceId}/flush`,
            {}
        );

        return res.data.id;
    }

    async getOrCreateWorkspaceIdWithSlug(
        slug: string,
        locales: string[]
    ): Promise<string> {
        try {
            return (
                await this.client.post(`/workspaces`, {
                    name: slug,
                    slug: slug,
                    enabledLocales: locales,
                    localeFallbacks: [],
                    ownerId: getStrict('databox.ownerId'),
                })
            ).data.id;
        } catch (e) {
            return this.getWorkspaceIdFromSlug(slug);
        }
    }

    async getWorkspaceIdFromSlug(slug: string): Promise<string> {
        const res = await this.client.get(`/workspaces-by-slug/${slug}`);

        return res.data.id;
    }
}

export function createDataboxClientFromConfig(logger: Logger): DataboxClient {
    return new DataboxClient(
        {
            apiUrl: getStrict('databox.url'),
            clientId: getStrict('databox.clientId'),
            clientSecret: getStrict('databox.clientSecret'),
            ownerId: getStrict('databox.ownerId'),
            verifySSL: getConfig('databox.verifySSL', true),
            scope: 'super-admin',
        },
        logger
    );
}

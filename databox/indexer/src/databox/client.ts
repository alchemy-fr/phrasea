import {AxiosInstance} from 'axios';
import {AssetInput, AttributeClass, AttributeDefinition, CollectionInput, RenditionClass} from "./types";
import {lockPromise} from "../lib/promise";
import {getConfig, getStrict} from "../configLoader";
import {Logger} from "winston";
import {createHttpClient} from "../lib/axios";
import {configureClientCredentialsGrantType, MemoryStorage, OAuthClient} from "@alchemy/auth";

function createApiClient(baseURL: string, clientId: string, clientSecret: string, verifySSL: boolean) {
    const oauthClient = new OAuthClient({
        clientId,
        clientSecret,
        baseUrl: `${baseURL}/oauth/v2`,
        storage: new MemoryStorage(),
    })

    const client = createHttpClient({
        baseURL,
        verifySSL,
        headers: {'Accept': 'application/ld+json'},
    });

    configureClientCredentialsGrantType(client, oauthClient);

    return {
        client,
        oauthClient,
    };
}

type ClientParameters = {
    apiUrl: string;
    clientId: string;
    clientSecret: string;
    scope: string;
    verifySSL: boolean;
    ownerId: string;
}

const maxTitleLength = 255;

const collectionKeyMap: Record<string, string> = {};

export class DataboxClient {
    private readonly client: AxiosInstance;
    private readonly oauthClient: OAuthClient;
    private readonly logger: Logger;
    private readonly clientId: string;
    private readonly clientSecret: string;
    private readonly ownerId: string;
    private readonly scope: string;
    private authPromise?: Promise<void>;

    constructor({
                    apiUrl,
                    clientId,
                    clientSecret,
                    scope,
                    ownerId,
                    verifySSL = true,
                }: ClientParameters, logger: Logger) {
        const {client, oauthClient} = createApiClient(apiUrl, clientId, clientSecret, verifySSL);
        this.client = client;
        this.oauthClient = oauthClient;
        this.clientId = clientId;
        this.clientSecret = clientSecret;
        this.ownerId = ownerId;
        this.scope = scope;
        this.logger = logger;
    }

    async createAsset(data: AssetInput): Promise<void> {
        if (data.workspaceId) {
            data.workspace = `/workspaces/${data.workspaceId}`;
            delete data.workspaceId;
        }

        if (data.title && data.title.length > maxTitleLength) {
            const dots = ` ... [truncated]`;
            data.title = data.title.substring(0, maxTitleLength - dots.length)+dots;
            this.logger.warn(`Title truncated for asset ${JSON.stringify(data)}`);
        }

        await this.client.post(`/assets`, {
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
            }
        });
    }

    async createCollection(key: string, data: CollectionInput): Promise<string> {
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
            return (await this.client.post(`/collections`, {
                ownerId: this.ownerId,
                ...data,
            })).data;
        });

        return collectionKeyMap[key] = r.id;
    }

    async createCollectionTreeBranch(data: CollectionInput[]): Promise<string> {
        let parentId: string = undefined;
        const previousKeys = [];
        for (let i = 0; i < data.length; ++i) {
            previousKeys.push(data[i].key);

            const key = previousKeys.join('/');
            const id = await this.createCollection(key, {
                ...data[i],
                key,
                parent: parentId,
            });
            parentId = `/collections/${id}`;
        }

        return parentId;
    }

    async createAttributeDefinition(key: string, data: Partial<AttributeDefinition>): Promise<AttributeDefinition> {
        const r = await lockPromise(`attr-def-${key}`, async () => {
            return (await this.client.post(`/attribute-definitions`, data)).data;
        });

        return r;
    }

    async createAttributeClass(key: string, data: Partial<AttributeClass>): Promise<AttributeClass> {
        const r = await lockPromise(`attr-class-${key}`, async () => {
            return (await this.client.post(`/attribute-classes`, data)).data;
        });

        return r;
    }

    async createRenditionClass(data): Promise<string> {
        const res = await this.client.post(`/rendition-classes`, data);

        return res.data.id;
    }

    async getRenditionClasses(workspaceId: string): Promise<RenditionClass[]> {
        const res = await this.client.get(`/rendition-classes`, {
            params: {
                workspaceId,
            }
        });

        return res.data['hydra:member'];
    }

    async createRenditionDefinition(data): Promise<void> {
        await this.client.post(`/rendition-definitions`, data);
    }

    async flushWorkspace(workspaceId: string): Promise<string> {
        const res = await this.client.post(`/workspaces/${workspaceId}/flush`, {});

        return res.data.id;
    }

    async getWorkspaceIdFromSlug(slug: string): Promise<string> {
        const res = await this.client.get(`/workspaces-by-slug/${slug}`);

        return res.data.id;
    }
}

export function createDataboxClientFromConfig(logger: Logger): DataboxClient {
    return new DataboxClient({
        apiUrl: getStrict('databox.url'),
        clientId: getStrict('databox.clientId'),
        clientSecret: getStrict('databox.clientSecret'),
        ownerId: getStrict('databox.ownerId'),
        verifySSL: getConfig('databox.verifySSL', true),
        scope: 'chuck-norris'
    }, logger);
}

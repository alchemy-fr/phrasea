import {AxiosInstance} from 'axios';
import {AssetInput, AttributeDefinition, CollectionInput, RenditionClass} from "./types";
import {lockPromise} from "../lib/promise";
import {getConfig, getStrict} from "../configLoader";
import {Logger} from "winston";
import {createHttpClient} from "../lib/axios";

const maxRetries = 10;
const retryDelay = 5000;

function createApiClient(baseURL: string, verifySSL: boolean) {
    return createHttpClient({
        baseURL,
        verifySSL,
        headers: {'Accept': 'application/ld+json'},
    });
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
    private readonly logger: Logger;
    private authenticated: boolean = false;
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
        this.client = createApiClient(apiUrl, verifySSL);
        this.clientId = clientId;
        this.clientSecret = clientSecret;
        this.ownerId = ownerId;
        this.scope = scope;
        this.logger = logger;
    }

    public async authenticate() {
        if (this.authPromise) {
            return this.authPromise;
        }

        return this.authPromise = new Promise<void>((resolve, reject) => {
            if (this.authenticated) {
                resolve();
                return;
            }

            this.logger.debug(`Authenticating to Databox...`);
            const attempt = async (retry: number = 0) => {
                try {
                    const res = await this.client.post(`/oauth/v2/token`, {
                        client_id: this.clientId,
                        client_secret: this.clientSecret,
                        grant_type: "client_credentials",
                        scope: this.scope,
                    });

                    const data = res.data as {
                        access_token: string;
                    };

                    this.authenticated = true;
                    this.client.defaults.headers.common['Authorization'] = `Bearer ${data.access_token}`;
                    this.logger.info(`Authenticated to Databox!`);

                    resolve();
                } catch (e) {
                    this.logger.warn(`Databox authentication error: ${e.toString()}`);
                    if (retry >= maxRetries) {
                        this.logger.error(`Too many retries for Databox authentication [${retry}]`);
                        reject(e);

                        return;
                    }

                    this.logger.info(`Retry Databox authentication [${retry}]`);

                    setTimeout(() => {
                        attempt(retry + 1);
                    }, retryDelay);
                }
            };

            attempt();
        });
    }

    async createAsset(data: AssetInput): Promise<void> {
        await this.authenticate();

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

    async deleteAsset(workspaceId: string, key: string): Promise<void> {
        await this.authenticate();

        await this.client.delete(`/assets-by-key`, {
            data: {
                workspaceId,
                key,
            }
        });
    }

    async createCollection(key: string, data: CollectionInput): Promise<string> {
        await this.authenticate();

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
        await this.authenticate();

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
        const r = await lockPromise(key, async () => {
            return (await this.client.post(`/attribute-definitions`, data)).data;
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
        await this.authenticate();

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

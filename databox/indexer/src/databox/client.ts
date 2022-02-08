import axios, {AxiosInstance} from 'axios';
import https from 'https';
import {AssetInput, CollectionInput} from "./types";
import {lockPromise} from "../lib/promise";
import {getConfig, getStrict} from "../configLoader";
import {Logger} from "winston";

const maxRetries = 10;
const retryDelay = 5000;

function createApiClient(baseURL: string, verifySSL: boolean) {
    return axios.create({
        baseURL,
        timeout: 10000,
        headers: {'Accept': 'application/ld+json'},
        httpsAgent: new https.Agent({
            rejectUnauthorized: verifySSL
        })
    });
}

type ClientParameters = {
    apiUrl: string;
    clientId: string;
    clientSecret: string;
    scope: string;
    verifySSL: boolean;
    workspaceId: string;
    collectionId?: string;
    ownerId: string;
}

const collectionKeyMap: Record<string, string> = {};

export class DataboxClient {
    private readonly client: AxiosInstance;
    private readonly logger: Logger;
    private authenticated: boolean = false;
    private clientId: string;
    private clientSecret: string;
    private workspaceId: string;
    private collectionId?: string;
    private ownerId: string;
    private scope: string;
    private authPromise?: Promise<void>;

    constructor({
                    apiUrl,
                    clientId,
                    clientSecret,
                    scope,
                    workspaceId,
                    ownerId,
                    collectionId,
                    verifySSL = true,
                }: ClientParameters, logger: Logger) {
        this.client = createApiClient(apiUrl, verifySSL);
        this.clientId = clientId;
        this.clientSecret = clientSecret;
        this.workspaceId = workspaceId;
        this.collectionId = collectionId;
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
                        attempt(retry  + 1);
                    }, retryDelay);
                }
            };

            attempt();
        });
    }

    async createAsset(data: AssetInput): Promise<void> {
        await this.authenticate();

        await this.client.post(`/assets`, {
            workspace: `/workspaces/${this.workspaceId}`,
            ownerId: this.ownerId,
            collection: this.collectionId ? `/collections/${this.collectionId}` : this.collectionId,
            ...data,
        });
    }

    async deleteAsset(key: string): Promise<void> {
        console.log('key', key);
        await this.authenticate();

        await this.client.delete(`/assets-by-key`, {
            data: {
                workspaceId: this.workspaceId,
                key,
            }
        });
    }

    async createCollection(key: string, data: CollectionInput): Promise<string> {
        await this.authenticate();

        if (collectionKeyMap[key]) {
            return collectionKeyMap[key];
        }

        const r = await lockPromise(key, async () => {
            return (await this.client.post(`/collections`, {
                workspace: `/workspaces/${this.workspaceId}`,
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
}

export function createDataboxClientFromConfig(logger: Logger): DataboxClient {
    return new DataboxClient({
        apiUrl: getStrict('databox.url'),
        clientId: getStrict('databox.clientId'),
        clientSecret: getStrict('databox.clientSecret'),
        workspaceId: getStrict('databox.workspaceId'),
        collectionId: getStrict('databox.clientSecret'),
        ownerId: getStrict('databox.ownerId'),
        verifySSL: getConfig('databox.verifySSL', true),
        scope: 'chuck-norris'
    }, logger);
}

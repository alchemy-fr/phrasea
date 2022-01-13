import axios, {AxiosInstance} from 'axios';
import https from 'https';
import {AssetInput} from "./types";

const maxRetries = 10;
const retryDelay = 5000;

function createApiClient(baseURL: string, verifySSL: boolean) {
    return axios.create({
        baseURL,
        timeout: 10000,
        headers: {'Accept': 'application/json'},
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

export class DataboxClient {
    private readonly client: AxiosInstance;
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
                }: ClientParameters) {
        this.client = createApiClient(apiUrl, verifySSL);
        this.clientId = clientId;
        this.clientSecret = clientSecret;
        this.workspaceId = workspaceId;
        this.collectionId = collectionId;
        this.ownerId = ownerId;
        this.scope = scope;
    }

    public async authenticate() {
        if (this.authPromise) {
            return this.authPromise;
        }

        this.authPromise = new Promise<void>((resolve, reject) => {
            if (this.authenticated) {
                resolve();
                return;
            }

            console.debug(`Authenticating to Databox...`);
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
                    console.info(`Authenticated to Databox!`);

                    resolve();
                } catch (e) {
                    console.warn(`Databox authentication error: ${e.toString()}`);
                    if (retry >= maxRetries) {
                        console.error(`Too many retries for Databox authentication [${retry}]`);
                        reject(e);

                        return;
                    }

                    console.info(`Retry Databox authentication [${retry}]`);

                    setTimeout(() => {
                        attempt(retry  + 1);
                    }, retryDelay);
                }
            };

            attempt();
        });
    }

    async postAsset(data: AssetInput) {
        await this.authenticate();

        await this.client.post(`/assets`, {
            ...data,
            workspace: `/workspaces/${this.workspaceId}`,
            ownerId: this.ownerId,
            collection: this.collectionId ? `/collections/${this.collectionId}` : this.collectionId,
        });
    }
}

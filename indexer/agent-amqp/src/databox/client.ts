import axios, {AxiosInstance} from 'axios';
import {AssetInput} from "./types";

function createApiClient(baseURL: string) {
    return axios.create({
        baseURL,
        timeout: 10000,
        headers: {'Accept': 'application/json'}
    });
}

type ClientParameters = {
    apiUrl: string;
    clientId: string;
    clientSecret: string;
    scope: string;
}

export class DataboxClient {
    private readonly client: AxiosInstance;
    private authenticated: boolean = false;
    private clientId: string;
    private clientSecret: string;
    private scope: string;
    private authPromise?: Promise<void>;

    constructor({
                    apiUrl,
                    clientId,
                    clientSecret,
                    scope,
                }: ClientParameters) {
        this.client = createApiClient(apiUrl);
        this.clientId = clientId;
        this.clientSecret = clientSecret;
        this.scope = scope;
    }

    public async authenticate() {
        if (this.authPromise) {
            return this.authPromise;
        }

        this.authPromise = new Promise<void>(async (resolve) => {
            if (this.authenticated) {
                resolve();
                return;
            }

            console.debug(`Authenticating to Databox...`);
            try {
                const res = await this.client.post(`/oauth/v2/token`, {
                    client_id: this.clientId,
                    client_secret: this.clientSecret,
                    grant_type: "client_credentials",
                    scope: this.scope,
                }) as {
                    access_token: string;
                };

                this.authenticated = true;
                this.client.defaults.headers.common['Authorization'] = `Bearer ${res.access_token}`;
                console.debug(`Authenticated!`);

                resolve();
            } catch (e) {
                console.log('e', e);

                throw e;
            }
        });
    }

    async postAsset(data: AssetInput) {
        await this.client.post(`/assets`, data);
    }
}

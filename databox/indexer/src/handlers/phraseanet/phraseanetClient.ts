import {AxiosInstance} from "axios";
import {getConfig, getStrict} from "../../configLoader";
import {
    PhraseanetCollection,
    PhraseanetConfig,
    PhraseanetMetaStruct,
    PhraseanetRecord,
    PhraseanetSubDef
} from "./types";
import {createHttpClient} from "../../lib/axios";

export function createPhraseanetClient(options: PhraseanetConfig) {
    const baseURL = getStrict('url', options);
    const token = getStrict('token', options);
    const verifySSL = getConfig('verifySSL', true, options);

    return createHttpClient({
        baseURL,
        params: {
            oauth_token: token,
        },
        verifySSL,
        timeout: 30000,
    });
}

export default class PhraseanetClient {
    private readonly client: AxiosInstance;
    private readonly searchQuery?: string;

    constructor(options: PhraseanetConfig) {
        this.client = createPhraseanetClient(options);
        this.searchQuery = options.searchQuery;
    }

    async getCollections(): Promise<PhraseanetCollection[]> {
        const res = await this.client.get(`/api/v1/me/collections`);

        return res.data.response.collections;
    }

    async search(params: Record<string, any>, offset: number = 0): Promise<PhraseanetRecord[]> {
        const res = await this.client.get('/api/v3/search/', {
            params: {
                offset,
                limit: 100,
                search_type: 0,
                query: this.searchQuery,
                include: [
                    'results.records.subdefs',
                    'results.records.caption',
                ],
                ...params,
            }
        });

        return res.data.response.results.records;
    }

    async getMetaStruct(databoxId: string): Promise<PhraseanetMetaStruct[]> {
        const res = await this.client.get(`/api/v1/databoxes/${databoxId}/metadatas/`);

        return res.data.response.document_metadatas;
    }

    async getSubDefinitions(): Promise<PhraseanetSubDef[]> {
        const res = await this.client.get(`/api/v1/me/subdefs`);

        const subdefs: PhraseanetSubDef[] = [];

        const defs = res.data.response.subdefs;
        Object.keys(defs).forEach(type => {
            const sd = defs[type];

            Object.keys(sd).forEach(name => {
                subdefs.push(sd[name]);
            });
        });

        return subdefs;
    }
}

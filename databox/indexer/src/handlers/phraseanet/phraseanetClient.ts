import {AxiosInstance} from "axios";
import {getConfig, getStrict} from "../../configLoader";
import {PhraseanetCollection, PhraseanetConfig, PhraseanetMetaStruct, PhraseanetRecord} from "./types";
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
    });
}

export default class PhraseanetClient {
    private readonly client: AxiosInstance;

    constructor(options: PhraseanetConfig) {
        this.client = createPhraseanetClient(options);
    }

    async getCollections(): Promise<PhraseanetCollection[]> {
        const res = await this.client.get(`/api/v1/me/collections`);

        return res.data.response.collections;
    }

    async search(offset: number = 0): Promise<PhraseanetRecord[]> {
        const res = await this.client.get('/api/v3/search/', {
            params: {
                offset,
                limit: 100,
                search_type: 0,
                include: [
                    'results.records.subdefs',
                    'results.records.caption',
                ]
            }
        });

        return res.data.response.results.records;
    }

    async getMetaStruct(): Promise<PhraseanetMetaStruct[]> {
        const res = await this.client.get(`/api/v1/databoxes/1/metadatas/`);

        return res.data.response.document_metadatas;
    }
}

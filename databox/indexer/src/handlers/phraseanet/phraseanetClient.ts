import {AxiosInstance} from 'axios';
import {getConfig, getStrict} from '../../configLoader';
import {
    PhraseanetCollection,
    PhraseanetConfig,
    PhraseanetMetaStruct,
    PhraseanetRecord,
    PhraseanetSubDef,
} from './types';
import {createHttpClient} from '../../lib/axios';

export function createPhraseanetClient(options: PhraseanetConfig) {
    const baseURL = getStrict('url', options);
    const token = getStrict('token', options);
    const verifySSL = getConfig('verifySSL', true, options);

    return createHttpClient({
        baseURL,
        headers: {
            Authorization: `OAuth ${token}`,
        },
        verifySSL,
        timeout: 60000,
    });
}

export default class PhraseanetClient {
    private readonly client: AxiosInstance;
    private readonly searchQuery?: string;
    private readonly searchOrder?: string;

    constructor(options: PhraseanetConfig) {
        this.client = createPhraseanetClient(options);
        this.searchQuery = options.searchQuery;
        this.searchOrder = options.searchOrder;
    }

    async getCollections(): Promise<PhraseanetCollection[]> {
        const res = await this.client.get(`/api/v1/me/collections`);

        return res.data.response.collections;
    }

    async search(
        params: Record<string, any>,
        offset: number = 0
    ): Promise<PhraseanetRecord[]> {
        if (this.searchOrder) {
            const [col, way] = this.searchOrder.split(',');
            params.sort = col;
            params.ord = way || 'asc';
        }

        const res = await this.client.get('/api/v3/search/', {
            params: {
                offset,
                limit: 100,
                search_type: 0,
                query: this.searchQuery,
                include: ['results.records.subdefs', 'results.records.caption'],
                ...params,
            },
        });

        return res.data.response.results.records;
    }

    async getMetaStruct(databoxId: string): Promise<PhraseanetMetaStruct[]> {
        const res = await this.client.get(
            `/api/v1/databoxes/${databoxId}/metadatas/`
        );

        return res.data.response.document_metadatas;
    }

    async getSubDefinitions(databoxId?: string): Promise<PhraseanetSubDef[]> {
        const dbid = typeof databoxId !== 'undefined' ? '/' + databoxId : '';
        const res = await this.client.get(`/api/v3/databoxes${dbid}/subdefs/`);

        const subdefs: PhraseanetSubDef[] = [];

        const dbxs = res.data.response.databoxes;
        Object.keys(dbxs).forEach(id => {
            const defs = dbxs[id].subdefs;
            Object.keys(defs).forEach(type => {
                const sd = defs[type];

                Object.keys(sd).forEach(name => {
                    subdefs.push({
                        ...sd[name],
                        type, // could be included by api, but for now add it here
                    });
                });
            });
        });

        return subdefs;
    }
}

import {AxiosInstance} from 'axios';
import {getConfig, getStrict} from '../../configLoader';
import {
    PhraseanetCollection,
    PhraseanetConfig,
    PhraseanetMetaStruct,
    PhraseanetRecord,
    PhraseanetStatusBitStruct,
    PhraseanetStory,
    PhraseanetSubDef,
} from './types';
import {createHttpClient} from '../../lib/axios';
import {
    PhraseanetSearchType,
    PhraseanetSearchTypeRecord,
    PhraseanetSearchTypeStory
} from "./shared";

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
    private readonly searchOrder?: string;
    private readonly instanceId?: string;       // todo: replace by api call

    constructor(options: PhraseanetConfig) {
        this.client = createPhraseanetClient(options);
        this.searchOrder = options.searchOrder;
        this.instanceId = options.instanceId;      // todo: replace by api call
    }

    async getInstanceId(): Promise<string> {
        // todo: replace by api call
        return this.instanceId ?? "";

        // todo: change api call to return instance_id
        // return (await this.client.get(`/api/v1/monitor/phraseanet`))
        //     .data.response.global_values.httpServer.siteId;
    }

    async getCollections(): Promise<PhraseanetCollection[]> {
        const res = await this.client.get(`/api/v1/me/collections`);

        return res.data.response.collections;
    }

    searchRecords(
        params: Record<string, any>,
        offset: number = 0,
        searchQuery: string
    ): Promise<PhraseanetRecord[]> {
        return this.search(params, offset, PhraseanetSearchTypeRecord, searchQuery) as unknown as Promise<PhraseanetRecord[]>;
    }

    searchStories(
        params: Record<string, any>,
        offset: number = 0,
        searchQuery: string
    ): Promise<PhraseanetStory[]> {
        return this.search(params, offset, PhraseanetSearchTypeStory, searchQuery) as unknown as Promise<PhraseanetStory[]>;
    }

    async search(
        params: Record<string, any>,
        offset: number = 0,
        searchType: PhraseanetSearchType,
        searchQuery: string
    ): Promise<PhraseanetRecord[] | PhraseanetStory[]> {
        if (this.searchOrder) {
            const [col, way] = this.searchOrder.split(',');
            params.sort = col;
            params.ord = way || 'asc';
        }

        const res = await this.client.get('/api/v3/search/', {
            params: {
                offset,
                limit: 100,
                search_type: searchType,
                query: searchQuery,
                story_children_limit:1000,
                include: [
                    'results.records.subdefs',
                    'results.records.caption',
                    'results.records.status',
                    'results.stories.caption',
                    'results.stories.status',
                    'results.stories.children'
                ],
                ...params,
            },
        });

        return searchType == PhraseanetSearchTypeRecord ? res.data.response.results.records : res.data.response.results.stories;
    }

    async getMetaStruct(databoxId: string): Promise<PhraseanetMetaStruct[]> {
        const res = await this.client.get(
            `/api/v1/databoxes/${databoxId}/metadatas/`
        );

        return res.data.response.document_metadatas;
    }

    async getStatusBitsStruct(databoxId: string): Promise<PhraseanetStatusBitStruct[]> {
        const res = await this.client.get(
            `/api/v1/databoxes/${databoxId}/status/`
        );

        return res.data.response.status;
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

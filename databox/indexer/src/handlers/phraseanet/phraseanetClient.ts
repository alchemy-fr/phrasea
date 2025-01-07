import {AxiosInstance} from 'axios';
import {getConfig, getStrict} from '../../configLoader';
import {
    PhraseanetCollection,
    PhraseanetConfig,
    PhraseanetDatabox,
    PhraseanetMetaStruct,
    PhraseanetStatusBitStruct,
    PhraseanetSubdefStruct,
    PhraseanetRecord,
    PhraseanetStory,
} from './types';
import {CPhraseanetRecord, CPhraseanetStory} from './CPhraseanetRecord';
import {createHttpClient} from '../../lib/axios';
import {PhraseanetSearchType} from './shared';
import * as winston from 'winston';

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
    private readonly id: string; // not the phraseanet conf.instanceId
    private readonly logger: winston.Logger;
    private _databoxIndexSet: boolean = false;
    private databoxIndex: Record<string, PhraseanetDatabox>;

    constructor(options: PhraseanetConfig, logger: winston.Logger) {
        this.client = createPhraseanetClient(options);
        this.searchOrder = options.searchOrder;
        this.id = btoa(options.url);
        this.logger = logger;
        this.databoxIndex = {};
    }

    async getDatabox(nameOrId: string) {
        if (!this._databoxIndexSet) {
            const dbi: Record<string, PhraseanetDatabox> = {};
            this.logger.info(`Fetching databoxes and collections`);
            for (const db of (await this.getDataboxes()) as PhraseanetDatabox[]) {
                db.collections = {};
                db.baseIds = [];
                db.metaStruct = {};
                dbi[db.name] = dbi[db.databox_id.toString()] = db;
            }
            for (const c of await this.getCollections()) {
                dbi[c.databox_id.toString()].collections[c.base_id.toString()] =
                    dbi[c.databox_id.toString()].collections[c.name] = c;
                dbi[c.databox_id.toString()].baseIds.push(c.base_id.toString());
            }
            this.databoxIndex = dbi;
            this._databoxIndexSet = true;
        }
        return this.databoxIndex[nameOrId];
    }

    getId(): string {
        return this.id;
    }

    async getDataboxes(): Promise<PhraseanetDatabox[]> {
        const res = await this.client.get(`/api/v1/databoxes/list`);

        return Object.values(res.data.response.databoxes);
    }

    async getCollections(): Promise<PhraseanetCollection[]> {
        const res = await this.client.get(`/api/v1/me/collections`);

        return res.data.response.collections;
    }

    searchRecords(
        params: Record<string, any>,
        offset: number = 0,
        searchQuery: string
    ): Promise<CPhraseanetRecord[]> {
        return this.search(
            params,
            offset,
            PhraseanetSearchType.Record,
            searchQuery,
            50
        ) as unknown as Promise<CPhraseanetRecord[]>;
    }

    searchStories(
        params: Record<string, any>,
        offset: number = 0,
        searchQuery: string
    ): Promise<CPhraseanetStory[]> {
        return this.search(
            params,
            offset,
            PhraseanetSearchType.Story,
            searchQuery,
            20
        ) as unknown as Promise<CPhraseanetStory[]>;
    }

    async search(
        params: Record<string, any>,
        offset: number = 0,
        searchType: PhraseanetSearchType,
        searchQuery: string,
        limit: number = 100
    ): Promise<(CPhraseanetRecord | CPhraseanetStory)[]> {
        if (this.searchOrder) {
            const [col, way] = this.searchOrder.split(',');
            params.sort = col;
            params.ord = way || 'asc';
        }

        let last_error = null;
        let ttry = 0;
        for(ttry=0; ttry<3; ttry++)
        {
            try {
                console.log(`Fetching search results...`);
                const res = await this.client.get('/api/v3/search/', {
                    params: {
                        offset,
                        limit: limit,
                        search_type: searchType,
                        query: searchQuery,
                        story_children_limit: 1000,
                        include: [
                            'results.records.subdefs',
                            'results.records.metadata',
                            'results.records.status',
                            'results.stories.caption',
                            'results.stories.status',
                            'results.stories.children',
                        ],
                        ...params,
                    },
                });
                const recs: (CPhraseanetRecord | CPhraseanetStory)[] = [];
                if (searchType === PhraseanetSearchType.Record) {
                    res.data.response.results.records.map((r: PhraseanetRecord) => {
                        recs.push(new CPhraseanetRecord(r, this));
                    });
                } else {
                    res.data.response.results.stories.map((s: PhraseanetStory) => {
                        recs.push(new CPhraseanetStory(s, this));
                    });
                }

                return recs;
            }
            catch(e)
            {
                last_error = e;
                console.log(`Failed to fetch search results, retrying in 5s...`);
                await new Promise(resolve => setTimeout(resolve, 5000));
            }
        }
        throw last_error;
    }

    async getMetaStruct(
        databoxId: string
    ): Promise<Record<string, PhraseanetMetaStruct>> {
        if (!this.databoxIndex[databoxId]._metaStructSet) {
            const res = await this.client.get(
                `/api/v1/databoxes/${databoxId}/metadatas/`
            );

            for (const k in res.data.response.document_metadatas) {
                // allow to access field struct by name
                this.databoxIndex[databoxId].metaStruct[
                    res.data.response.document_metadatas[k].name
                ] = res.data.response.document_metadatas[k];
            }
            this.databoxIndex[databoxId]._metaStructSet = true;
        }

        return this.databoxIndex[databoxId].metaStruct;
    }

    async getStatusBitsStruct(
        databoxId: string
    ): Promise<PhraseanetStatusBitStruct[]> {
        const res = await this.client.get(
            `/api/v1/databoxes/${databoxId}/status/`
        );

        return res.data.response.status;
    }

    async getSubdefsStruct(
        databoxId?: string
    ): Promise<PhraseanetSubdefStruct[]> {
        const dbid = typeof databoxId !== 'undefined' ? '/' + databoxId : '';
        const res = await this.client.get(`/api/v3/databoxes${dbid}/subdefs/`);

        const subdefs: PhraseanetSubdefStruct[] = [];

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

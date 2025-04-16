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

export const ORDER_ASC = 'asc';
export const ORDER_DESC = 'desc';

export default class PhraseanetClient {
    private readonly client: AxiosInstance;
    private readonly sortField: string;
    private readonly sortOrder: string;
    private readonly id: string; // not the phraseanet conf.instanceId
    private readonly logger: winston.Logger;
    private _databoxIndexSet: boolean = false;
    private databoxIndex: Record<string, PhraseanetDatabox>;

    constructor(options: PhraseanetConfig, logger: winston.Logger) {
        this.client = createPhraseanetClient(options);
        const [f, o] = (options.searchOrder ?? '').split(',');

        this.sortField = f ?? 'record_id';
        this.sortOrder = (o ?? 'asc').toLowerCase();

        if (
            this.sortField != 'record_id' ||
            (this.sortOrder != ORDER_ASC && this.sortOrder != ORDER_DESC)
        ) {
            throw new Error(
                `searchOrder must be 'record_id,asc' or 'record_id,desc', got '${options.searchOrder}'`
            );
        }
        this.id = btoa(options.url);
        this.logger = logger;
        this.databoxIndex = {};
    }

    public getSortOrder(): string {
        return this.sortOrder;
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

    async searchRecords(
        params: Record<string, any>,
        offset: number = 0,
        limit: number = 50,
        searchQuery: string
    ): Promise<CPhraseanetRecord[]> {
        return this.search(
            params,
            offset,
            PhraseanetSearchType.Record,
            searchQuery,
            limit
        ) as unknown as Promise<CPhraseanetRecord[]>;
    }

    async searchStories(
        params: Record<string, any>,
        offset: number = 0,
        limit: number = 20,
        searchQuery: string
    ): Promise<CPhraseanetStory[]> {
        return this.search(
            params,
            offset,
            PhraseanetSearchType.Story,
            searchQuery,
            limit
        ) as unknown as Promise<CPhraseanetStory[]>;
    }

    async search(
        params: Record<string, any>,
        offset: number = 0,
        searchType: PhraseanetSearchType,
        searchQuery: string,
        limit: number = 100
    ): Promise<(CPhraseanetRecord | CPhraseanetStory)[]> {
        let last_error = null;
        let ttry = 0;
        for (ttry = 0; ttry < 3; ttry++) {
            try {
                this.logger.info(`Fetching search results...`);
                const res = await this.client.get('/api/v3/search/', {
                    params: {
                        offset,
                        limit: limit,
                        search_type: searchType,
                        query: searchQuery,
                        include: [
                            'results.records.subdefs',
                            'results.records.metadata',
                            'results.records.status',
                            'results.stories.metadata',
                            'results.stories.status',
                        ],
                        sort: this.sortField,
                        ord: this.sortOrder,
                        ...params,
                    },
                });

                const recs: (CPhraseanetRecord | CPhraseanetStory)[] = [];
                if (searchType === PhraseanetSearchType.Record) {
                    res.data.response.results.records.map(
                        (r: PhraseanetRecord) => {
                            recs.push(new CPhraseanetRecord(r, this));
                        }
                    );
                } else {
                    res.data.response.results.stories.map(
                        (s: PhraseanetStory) => {
                            recs.push(new CPhraseanetStory(s, this));
                        }
                    );
                }

                return recs;
            } catch (e) {
                last_error = e;
                this.logger.warn(
                    `Failed to fetch search results, retrying in 5s...`
                );
                await new Promise(resolve => setTimeout(resolve, 5000));
            }
        }
        throw last_error;
    }

    async *getStoryChildren(
        databoxId: string,
        storyId: string
    ): AsyncGenerator<string> {
        let offset = 0;
        do {
            const res = await this.client.get(
                `/api/v3/stories/${databoxId}/${storyId}/children`,
                {
                    params: {
                        offset: offset,
                        limit: 50,
                    },
                }
            );
            if (
                !Array.isArray(res.data.response) ||
                res.data.response.length === 0
            ) {
                return;
            }
            for (const recordUri of res.data.response) {
                // -- requesting a uri is the way to get record_id, but it's too slow
                // r = this.client.get(recordUri);
                // const recordId = r.data.response.record_id;

                // -- we known that recordUri is like: "/api/v3/records/{sbas_id}/{record_id}/"
                yield recordUri.split('/')[5];
            }
            if (res.data.response.length < 50) {
                return;
            }
            offset += 50;
        } while (true);
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

import axios, {AxiosInstance} from 'axios';
import AxiosMockAdapter from 'axios-mock-adapter';
import {createTestLogger, TestLogger} from '../../../helpers/logger';
import {PhraseanetConfig} from '../../../../src/handlers/phraseanet/types';
import {makeRecord, makeStory, makeSubdef} from '../../../helpers/phraseanet';

const holder = vi.hoisted(() => ({
    instance: undefined as unknown as AxiosInstance,
    createHttpClientArgs: undefined as any,
}));

// The real client wraps axios with axios-retry; the suite targets the routes
// and parameters the Phraseanet client emits, so it gets a bare instance.
vi.mock('../../../../src/lib/axios', () => ({
    createHttpClient: (args: any) => {
        holder.createHttpClientArgs = args;

        return holder.instance;
    },
}));

const {
    default: PhraseanetClient,
    createPhraseanetClient,
    ORDER_ASC,
    ORDER_DESC,
} = await import('../../../../src/handlers/phraseanet/phraseanetClient');

let mock: AxiosMockAdapter;
let logger: TestLogger;

const config = (overrides: Partial<PhraseanetConfig> = {}): PhraseanetConfig =>
    ({
        url: 'https://phraseanet.test',
        token: 'oauth-token',
        verifySSL: false,
        searchOrder: 'record_id,asc',
        databoxMapping: [],
        ...overrides,
    }) as PhraseanetConfig;

const build = (overrides: Partial<PhraseanetConfig> = {}) =>
    new PhraseanetClient(config(overrides), logger);

beforeEach(() => {
    holder.instance = axios.create();
    mock = new AxiosMockAdapter(holder.instance, {onNoMatch: 'throwException'});
    logger = createTestLogger();
});

describe('createPhraseanetClient', () => {
    it('sets the base URL, the OAuth header and verifySSL', () => {
        createPhraseanetClient(config());

        expect(holder.createHttpClientArgs).toMatchObject({
            baseURL: 'https://phraseanet.test',
            verifySSL: false,
            timeout: 60000,
            headers: {Authorization: 'OAuth oauth-token'},
        });
    });

    it('defaults verifySSL to true', () => {
        createPhraseanetClient(config({verifySSL: undefined}));

        expect(holder.createHttpClientArgs.verifySSL).toBe(true);
    });
});

describe('searchOrder validation', () => {
    it('accepts record_id,asc', () => {
        expect(build({searchOrder: 'record_id,asc'}).getSortOrder()).toEqual(
            ORDER_ASC
        );
    });

    it('accepts record_id,desc', () => {
        expect(build({searchOrder: 'record_id,desc'}).getSortOrder()).toEqual(
            ORDER_DESC
        );
    });

    it('is case-insensitive on the direction', () => {
        expect(build({searchOrder: 'record_id,DESC'}).getSortOrder()).toEqual(
            ORDER_DESC
        );
    });

    it('falls back to record_id,asc when searchOrder is unset', () => {
        // `(undefined ?? '').split(',')` yields [''], so the fields were empty
        // strings rather than undefined and the `??` fallbacks never fired: a
        // location that omitted searchOrder failed to construct.
        expect(build({searchOrder: undefined}).getSortOrder()).toEqual(
            ORDER_ASC
        );
    });

    it('falls back to record_id,asc on an empty searchOrder', () => {
        expect(build({searchOrder: ''}).getSortOrder()).toEqual(ORDER_ASC);
    });

    it('defaults the direction to asc when only the field is given', () => {
        expect(build({searchOrder: 'record_id'}).getSortOrder()).toEqual(
            ORDER_ASC
        );
    });

    it('defaults the direction to asc on a trailing comma', () => {
        expect(build({searchOrder: 'record_id,'}).getSortOrder()).toEqual(
            ORDER_ASC
        );
    });

    it.each(['title,asc', 'record_id,random', 'nonsense'])(
        'rejects %p',
        value => {
            expect(() => build({searchOrder: value})).toThrow(
                /searchOrder must be 'record_id,asc' or 'record_id,desc'/
            );
        }
    );
});

describe('getId', () => {
    it('is the base64 of the instance URL', () => {
        expect(build().getId()).toEqual(btoa('https://phraseanet.test'));
    });
});

describe('structure endpoints', () => {
    it('lists databoxes as an array', async () => {
        mock.onGet('/api/v1/databoxes/list').reply(200, {
            response: {
                databoxes: {
                    '1': {databox_id: '1', name: 'db1'},
                    '2': {databox_id: '2', name: 'db2'},
                },
            },
        });

        expect(await build().getDataboxes()).toEqual([
            {databox_id: '1', name: 'db1'},
            {databox_id: '2', name: 'db2'},
        ]);
    });

    it('lists the collections visible to the token', async () => {
        mock.onGet('/api/v1/me/collections').reply(200, {
            response: {collections: [{base_id: '1'}]},
        });

        expect(await build().getCollections()).toEqual([{base_id: '1'}]);
    });

    it('lists the collections of one databox', async () => {
        mock.onGet('/api/v1/databoxes/7/collections/').reply(200, {
            response: {collections: {a: {base_id: '1'}}},
        });

        expect(await build().getCollectionsForDatabox('7')).toEqual([
            {base_id: '1'},
        ]);
    });

    it('reads the status bits structure', async () => {
        mock.onGet('/api/v1/databoxes/7/status/').reply(200, {
            response: {status: [{bit: 4, label_on: 'on', label_off: 'off'}]},
        });

        expect(await build().getStatusBitsStruct('7')).toEqual([
            {bit: 4, label_on: 'on', label_off: 'off'},
        ]);
    });

    it('flattens the subdefs structure and injects the type', async () => {
        mock.onGet('/api/v3/databoxes/7/subdefs/').reply(200, {
            response: {
                databoxes: {
                    '7': {
                        subdefs: {
                            image: {
                                preview: {name: 'preview', class: 'preview'},
                                thumbnail: {
                                    name: 'thumbnail',
                                    class: 'thumbnail',
                                },
                            },
                            video: {
                                preview: {name: 'preview', class: 'preview'},
                            },
                        },
                    },
                },
            },
        });

        expect(await build().getSubdefsStruct('7')).toEqual([
            {name: 'preview', class: 'preview', type: 'image'},
            {name: 'thumbnail', class: 'thumbnail', type: 'image'},
            {name: 'preview', class: 'preview', type: 'video'},
        ]);
    });

    it('queries every databox when no id is given', async () => {
        mock.onGet('/api/v3/databoxes/subdefs/').reply(200, {
            response: {databoxes: {}},
        });

        expect(await build().getSubdefsStruct()).toEqual([]);
    });
});

describe('getDatabox', () => {
    const reply = () => {
        mock.onGet('/api/v1/databoxes/list').reply(200, {
            response: {databoxes: {'1': {databox_id: '1', name: 'db1'}}},
        });
        mock.onGet('/api/v1/me/collections').reply(200, {
            response: {
                collections: [
                    {databox_id: '1', base_id: '10', name: 'coll-a'},
                    {databox_id: '1', base_id: '11', name: 'coll-b'},
                ],
            },
        });
    };

    it('indexes a databox by name and by id, with its collections', async () => {
        reply();
        const client = build();

        const byName = await client.getDatabox('db1');
        const byId = await client.getDatabox('1');

        expect(byName).toBe(byId);
        expect(byName.baseIds).toEqual(['10', '11']);
        expect(byName.collections['10'].name).toEqual('coll-a');
        expect(byName.collections['coll-b'].base_id).toEqual('11');
    });

    it('fetches the structure only once', async () => {
        reply();
        const client = build();

        await client.getDatabox('db1');
        await client.getDatabox('db1');

        expect(
            mock.history.get.filter(r => r.url?.includes('list'))
        ).toHaveLength(1);
    });
});

describe('getMetaStruct', () => {
    it('indexes the metadata structure by field name and caches it', async () => {
        mock.onGet('/api/v1/databoxes/list').reply(200, {
            response: {databoxes: {'1': {databox_id: '1', name: 'db1'}}},
        });
        mock.onGet('/api/v1/me/collections').reply(200, {
            response: {collections: []},
        });
        mock.onGet('/api/v1/databoxes/1/metadatas/').reply(200, {
            response: {
                document_metadatas: {
                    '1': {id: '1', name: 'Title'},
                    '2': {id: '2', name: 'Subject'},
                },
            },
        });

        const client = build();
        await client.getDatabox('1');

        expect(Object.keys(await client.getMetaStruct('1')).sort()).toEqual([
            'Subject',
            'Title',
        ]);

        await client.getMetaStruct('1');

        expect(
            mock.history.get.filter(r => r.url?.includes('metadatas'))
        ).toHaveLength(1);
    });
});

describe('search', () => {
    it('sends the sort, the pagination and the include list', async () => {
        mock.onGet('/api/v3/search/').reply(200, {
            response: {results: {records: [makeRecord()]}},
        });

        const records = await build({
            searchOrder: 'record_id,desc',
        }).searchRecords({bases: [1]}, 100, 50, 'a query', false);

        expect(records).toHaveLength(1);
        expect(mock.history.get[0].params).toMatchObject({
            offset: 100,
            limit: 50,
            search_type: 0,
            query: 'a query',
            sort: 'record_id',
            ord: 'desc',
            bases: [1],
        });
        expect(mock.history.get[0].params.include).toContain(
            'results.records.subdefs'
        );
    });

    it('wraps records into CPhraseanetRecord', async () => {
        mock.onGet('/api/v3/search/').reply(200, {
            response: {
                results: {
                    records: [
                        makeRecord({
                            record_id: '100',
                            subdefs: [makeSubdef({name: 'preview'})],
                        }),
                    ],
                },
            },
        });

        const [record] = await build().searchRecords({}, 0, 50, '', false);

        expect(record.record_id).toEqual('100');
        expect(await record.getSubdef('preview')).toMatchObject({
            name: 'preview',
        });
    });

    it('defaults a missing stories field when importStories is off', async () => {
        mock.onGet('/api/v3/search/').reply(200, {
            response: {
                results: {records: [makeRecord({stories: undefined as any})]},
            },
        });

        const [record] = await build().searchRecords({}, 0, 50, '', false);

        expect(record.stories).toEqual([]);
    });

    it('searches stories with search_type 1', async () => {
        mock.onGet('/api/v3/search/').reply(200, {
            response: {results: {stories: [makeStory({story_id: '900'})]}},
        });

        const [story] = await build().searchStories({}, 0, 20, '');

        expect(story.story_id).toEqual('900');
        expect(mock.history.get[0].params).toMatchObject({
            search_type: 1,
            limit: 20,
        });
    });
});

describe('getStoryChildren', () => {
    const collect = async (gen: AsyncGenerator<string>) => {
        const out: string[] = [];
        for await (const id of gen) {
            out.push(id);
        }

        return out;
    };

    it('extracts the record id out of each returned URI', async () => {
        mock.onGet('/api/v3/stories/1/900/children').reply(200, {
            response: ['/api/v3/records/1/101/', '/api/v3/records/1/102/'],
        });

        expect(await collect(build().getStoryChildren('1', '900'))).toEqual([
            '101',
            '102',
        ]);
    });

    it('stops on an empty page', async () => {
        mock.onGet('/api/v3/stories/1/900/children').reply(200, {
            response: [],
        });

        expect(await collect(build().getStoryChildren('1', '900'))).toEqual([]);
    });

    it('pages until a short page comes back', async () => {
        const page = (from: number, count: number) =>
            Array.from(
                {length: count},
                (_, i) => `/api/v3/records/1/${from + i}/`
            );

        mock.onGet('/api/v3/stories/1/900/children')
            .replyOnce(200, {response: page(100, 50)})
            .onGet('/api/v3/stories/1/900/children')
            .replyOnce(200, {response: page(150, 2)});

        const ids = await collect(build().getStoryChildren('1', '900'));

        expect(ids).toHaveLength(52);
        expect(mock.history.get).toHaveLength(2);
        expect(mock.history.get[1].params).toMatchObject({offset: 50});
    });
});

describe('getRecordEmbeds', () => {
    it('reads the v1 embed route', async () => {
        mock.onGet('/api/v1/records/1/100/embed/').reply(200, {
            response: {embed: []},
        });

        expect(await build().getRecordEmbeds(1, 100)).toEqual({
            response: {embed: []},
        });
    });
});

import {
    CPhraseanetRecord,
    CPhraseanetStory,
} from '../../../../src/handlers/phraseanet/CPhraseanetRecord';
import {CPhraseanetMetadata} from '../../../../src/handlers/phraseanet/CPhraseanetMetadata';
import {CPhraseanetSubdef} from '../../../../src/handlers/phraseanet/CPhraseanetSubdef';
import {
    fakePhraseanetClient,
    makeMetadata,
    makeRecord,
    makeStatusBit,
    makeStory,
    makeSubdef,
} from '../../../helpers/phraseanet';

const record = (overrides = {}) =>
    new CPhraseanetRecord(makeRecord(overrides), fakePhraseanetClient);

describe('CPhraseanetRecord construction', () => {
    it('copies the scalar fields', () => {
        const r = record({record_id: '42', title: 'My title'});

        expect(r.record_id).toEqual('42');
        expect(r.title).toEqual('My title');
        expect(r.databox_id).toEqual('1');
        expect(r.phrasea_type).toEqual('image');
        expect(r.mime_type).toEqual('image/jpeg');
    });

    it('maps stories down to their ids', () => {
        const r = record({
            stories: [{story_id: '900'}, {story_id: '901'}],
        });

        expect(r.stories).toEqual(['900', '901']);
    });

    it('defaults stories to an empty list', () => {
        expect(record().stories).toEqual([]);
    });
});

describe('metadata indexing', () => {
    it('groups repeated fields into values and joins them into value', () => {
        const r = record({
            metadata: [
                makeMetadata('Subject', 'beta'),
                makeMetadata('Subject', 'alpha'),
            ],
        });

        expect(r.metadata.Subject.values).toEqual(['alpha', 'beta']);
        expect(r.metadata.Subject.value).toEqual('alpha ; beta');
    });

    it('sorts values case-insensitively', () => {
        const r = record({
            metadata: [
                makeMetadata('Subject', 'Zebra'),
                makeMetadata('Subject', 'apple'),
                makeMetadata('Subject', 'Mango'),
            ],
        });

        expect(r.metadata.Subject.values).toEqual(['apple', 'Mango', 'Zebra']);
    });

    it('drops blank values', () => {
        const r = record({
            metadata: [
                makeMetadata('Title', '   '),
                makeMetadata('Title', ''),
                makeMetadata('Other', 'kept'),
            ],
        });

        expect(r.metadata.Title).toBeUndefined();
        expect(r.metadata.Other.value).toEqual('kept');
    });

    it('keeps the structure id of the first occurrence', () => {
        const r = record({
            metadata: [
                makeMetadata('Subject', 'a', '7'),
                makeMetadata('Subject', 'b', '9'),
            ],
        });

        expect(r.metadata.Subject.meta_structure_id).toEqual('7');
    });

    it('tolerates a record without metadata', () => {
        expect(record({metadata: undefined as any}).metadata).toEqual({});
    });
});

describe('getMetadata', () => {
    it('returns the indexed metadata', async () => {
        const r = record({metadata: [makeMetadata('Title', 'A title')]});

        expect((await r.getMetadata('Title')).value).toEqual('A title');
    });

    it('returns the default value wrapped in a metadata object', async () => {
        const m = await record().getMetadata('Missing', 'fallback');

        expect(m.value).toEqual('fallback');
        expect(m.values).toEqual(['fallback']);
    });

    it('accepts an empty string as a default', async () => {
        expect((await record().getMetadata('Missing', '')).value).toEqual('');
    });

    it('returns a null metadata when there is no default', async () => {
        expect(await record().getMetadata('Missing')).toEqual(
            CPhraseanetMetadata.NullMetadata
        );
    });
});

describe('getStatus', () => {
    const r = () =>
        record({
            status: [makeStatusBit(4, true), makeStatusBit(5, false)],
        });

    it('returns true/false by default', async () => {
        expect(await r().getStatus(4)).toBe(true);
        expect(await r().getStatus(5)).toBe(false);
    });

    it('returns the supplied labels', async () => {
        expect(await r().getStatus(4, 'on', 'off')).toEqual('on');
        expect(await r().getStatus(5, 'on', 'off')).toEqual('off');
    });

    it('falls back to the false value for an unknown bit', async () => {
        expect(await r().getStatus(9)).toBe(false);
        expect(await r().getStatus(9, 'on', 'off')).toEqual('off');
    });
});

describe('getSubdef', () => {
    it('returns the matching subdef', async () => {
        const r = record({
            subdefs: [makeSubdef({name: 'preview', width: 640})],
        });

        const sd = await r.getSubdef('preview');

        expect(sd.name).toEqual('preview');
        expect(sd.width).toEqual(640);
        expect(sd.permalink?.url).toEqual(
            'https://phraseanet.test/permalink/preview'
        );
    });

    it('returns the null subdef for an unknown name', async () => {
        expect(await record().getSubdef('nope')).toBe(
            CPhraseanetSubdef.NullSubdef
        );
    });
});

describe('CPhraseanetStory', () => {
    it('carries the story fields', () => {
        const s = new CPhraseanetStory(
            makeStory({story_id: '900', children_total: 3, cover_record_id: 7}),
            fakePhraseanetClient
        );

        expect(s.story_id).toEqual('900');
        expect(s.children_total).toEqual(3);
        expect(s.cover_record_id).toEqual(7);
        expect(s.phrasea_type).toEqual('story');
    });

    it('wraps children into CPhraseanetRecord instances', () => {
        const s = new CPhraseanetStory(
            makeStory({
                children: [
                    makeRecord({record_id: '1'}),
                    makeRecord({record_id: '2'}),
                ],
            }),
            fakePhraseanetClient
        );

        expect(s.children).toHaveLength(2);
        expect(s.children[0]).toBeInstanceOf(CPhraseanetRecord);
        expect(s.children.map(c => c.record_id)).toEqual(['1', '2']);
    });

    it('defaults children to an empty list', () => {
        const s = new CPhraseanetStory(
            makeStory({children: undefined as any}),
            fakePhraseanetClient
        );

        expect(s.children).toEqual([]);
    });

    it('indexes its own metadata like a record does', () => {
        const s = new CPhraseanetStory(
            makeStory({
                metadata: [
                    makeMetadata('Title', 'b'),
                    makeMetadata('Title', 'a'),
                ],
            }),
            fakePhraseanetClient
        );

        expect(s.metadata.Title.value).toEqual('a ; b');
    });

    it('delegates getChildren to the client', async () => {
        const client = {
            getStoryChildren: vi.fn(async () => ['child']),
        } as any;
        const s = new CPhraseanetStory(
            makeStory({databox_id: '1', story_id: '900'}),
            client
        );

        expect(await s.getChildren()).toEqual(['child']);
        expect(client.getStoryChildren).toHaveBeenCalledWith('1', '900');
    });
});

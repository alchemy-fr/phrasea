import {
    PhraseanetMetadata,
    PhraseanetRecord,
    PhraseanetStatusBit,
    PhraseanetStory,
    PhraseanetSubdef,
} from '../../src/handlers/phraseanet/types';

export function makeSubdef(
    overrides: Partial<PhraseanetSubdef> & {name: string}
): PhraseanetSubdef {
    return {
        height: 100,
        width: 200,
        filesize: 1234,
        player_type: 'IMAGE',
        mime_type: 'image/jpeg',
        created_on: '2024-01-01T00:00:00+01:00',
        updated_on: '2024-01-02T00:00:00+01:00',
        url: `https://phraseanet.test/${overrides.name}`,
        permalink: {
            url: `https://phraseanet.test/permalink/${overrides.name}`,
        },
        ...overrides,
    };
}

export function makeMetadata(
    name: string,
    value: string,
    metaStructureId = '1'
): PhraseanetMetadata {
    return {
        meta_structure_id: metaStructureId,
        name,
        value,
    };
}

export function makeStatusBit(
    bit: number,
    state: boolean
): PhraseanetStatusBit {
    return {bit, state};
}

export function makeRecord(
    overrides: Partial<PhraseanetRecord> = {}
): PhraseanetRecord {
    return {
        resource_id: '1_100',
        databox_id: '1',
        base_id: '1',
        record_id: '100',
        collection_id: '1',
        uuid: '00000000-0000-0000-0000-000000000100',
        title: 'A record',
        original_name: 'record.jpg',
        mime_type: 'image/jpeg',
        phrasea_type: 'image',
        type: 'image',
        created_on: '2024-01-01T00:00:00+01:00',
        updated_on: '2024-01-02T00:00:00+01:00',
        subdefs: [],
        status: [],
        metadata: [],
        stories: [],
        ...overrides,
    };
}

export function makeStory(
    overrides: Partial<PhraseanetStory> = {}
): PhraseanetStory {
    return {
        resource_id: '1_900',
        databox_id: '1',
        base_id: '1',
        story_id: '900',
        collection_id: '1',
        uuid: '00000000-0000-0000-0000-000000000900',
        title: 'A story',
        original_name: 'story',
        mime_type: '',
        created_on: '2024-01-01T00:00:00+01:00',
        updated_on: '2024-01-02T00:00:00+01:00',
        children_total: 0,
        cover_record_id: null,
        subdefs: [],
        status: [],
        metadata: [],
        children: [],
        ...overrides,
    };
}

/**
 * The CPhraseanet* constructors keep a reference to the client but only
 * CPhraseanetStory.getChildren() ever dereferences it.
 */
export const fakePhraseanetClient = {} as any;

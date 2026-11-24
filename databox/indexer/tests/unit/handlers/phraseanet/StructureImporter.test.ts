import {importStatusBitsStructure} from '../../../../src/handlers/phraseanet/StructureImporter';
import PhraseanetClient from '../../../../src/handlers/phraseanet/phraseanetClient';
import {PhraseanetStatusBitStruct} from '../../../../src/handlers/phraseanet/types';
import {
    createFakeDataboxClient,
    FakeDataboxClient,
} from '../../../helpers/databox';
import {createTestLogger, TestLogger} from '../../../helpers/logger';

let databoxClient: FakeDataboxClient;
let logger: TestLogger;

beforeEach(() => {
    databoxClient = createFakeDataboxClient();
    logger = createTestLogger();
});

const phraseanetClient = (statusBits: PhraseanetStatusBitStruct[]) =>
    ({
        getId: () => 'phr',
        getStatusBitsStruct: vi.fn(async () => statusBits),
    }) as unknown as PhraseanetClient;

const statusBit = (
    bit: number,
    label_on: string,
    label_off = ''
): PhraseanetStatusBitStruct => ({bit, label_on, label_off});

const run = (statusBits: PhraseanetStatusBitStruct[]) =>
    importStatusBitsStructure({
        databoxClient,
        workspaceId: 'ws-1',
        phraseanetDataboxId: '1',
        phraseanetClient: phraseanetClient(statusBits),
        idempotencePrefixes: {attributeDefinition: 'phr_'},
        attrPolicy: '/attribute-policies/pol-1',
        logger,
    });

describe('importStatusBitsStructure', () => {
    it('creates the "Tags" list, its attribute and a value per status bit', async () => {
        databoxClient.createEntityList.mockResolvedValue({
            id: 'list-1',
            name: 'Tags',
        });
        databoxClient.createAttributeDefinition.mockResolvedValue({
            id: 'def-tags',
        });
        let n = 0;
        databoxClient.createAttributeEntity.mockImplementation(async () => ({
            id: `ent-${++n}`,
        }));

        const index = await run([
            statusBit(4, 'Online'),
            statusBit(5, '', 'Offline'),
        ]);

        expect(index).toEqual({
            definitionId: 'def-tags',
            entities: {4: 'ent-1', 5: 'ent-2'},
        });
        expect(databoxClient.createEntityList).toHaveBeenCalledWith(
            'ws-1_Tags',
            {
                workspace: '/workspaces/ws-1',
                name: 'Tags',
                withColors: true,
                withTranslations: true,
            }
        );
        expect(databoxClient.createAttributeDefinition).toHaveBeenCalledWith(
            'ws-1_status_bits',
            {
                key: 'phr_status_bits',
                name: 'Tags',
                type: 'entity',
                entityList: '/entity-lists/list-1',
                multiple: true,
                facetEnabled: true,
                searchable: true,
                editable: true,
                translatable: false,
                target: 3,
                workspace: '/workspaces/ws-1',
                policy: '/attribute-policies/pol-1',
            }
        );
        expect(databoxClient.createAttributeEntity.mock.calls).toEqual([
            [
                'list-1_Online',
                {list: '/entity-lists/list-1', value: 'Online', position: 4},
            ],
            [
                'list-1_Offline',
                {list: '/entity-lists/list-1', value: 'Offline', position: 5},
            ],
        ]);
    });

    it('reuses the list, the attribute and the values already in the workspace', async () => {
        databoxClient.getEntityLists.mockResolvedValue([
            {id: 'other', name: 'Tags (old)'},
            {id: 'list-1', name: 'Tags'},
        ]);
        databoxClient.getAttributeDefinitions.mockResolvedValue([
            {id: 'single', multiple: false, slug: 'tag'},
            {id: 'def-tags', multiple: true, slug: 'tags'},
        ]);
        databoxClient.getAttributeEntities.mockResolvedValue([
            {id: 'ent-online', value: 'Online'},
        ]);
        databoxClient.createAttributeEntity.mockResolvedValue({id: 'ent-new'});

        const index = await run([
            statusBit(4, 'Online'),
            statusBit(6, 'New'),
            statusBit(7, 'Online'),
        ]);

        expect(index).toEqual({
            definitionId: 'def-tags',
            entities: {4: 'ent-online', 6: 'ent-new', 7: 'ent-online'},
        });
        expect(databoxClient.getEntityLists).toHaveBeenCalledWith(
            'ws-1',
            'Tags'
        );
        expect(databoxClient.getAttributeDefinitions).toHaveBeenCalledWith({
            workspaceId: 'ws-1',
            entityListId: 'list-1',
        });
        expect(databoxClient.getAttributeEntities).toHaveBeenCalledWith(
            'list-1'
        );
        expect(databoxClient.createEntityList).not.toHaveBeenCalled();
        expect(databoxClient.createAttributeDefinition).not.toHaveBeenCalled();
        expect(databoxClient.createAttributeEntity).toHaveBeenCalledTimes(1);
        expect(databoxClient.createAttributeEntity).toHaveBeenCalledWith(
            'list-1_New',
            {list: '/entity-lists/list-1', value: 'New', position: 6}
        );
    });

    it('names the attribute differently when "tags" is taken by another one', async () => {
        databoxClient.getAttributeDefinitions.mockImplementation(
            async (params: Record<string, any>) =>
                params.slugs
                    ? [{id: 'other', multiple: false, slug: 'tags'}]
                    : []
        );

        await run([statusBit(4, 'Online')]);

        expect(databoxClient.getAttributeDefinitions).toHaveBeenLastCalledWith({
            workspaceId: 'ws-1',
            slugs: ['tags'],
        });
        expect(
            databoxClient.createAttributeDefinition.mock.calls[0][1]
        ).toMatchObject({name: 'Tags (Phraseanet)'});
    });

    it('skips the bits without label and creates nothing without any bit', async () => {
        const index = await run([statusBit(4, ' ', '')]);

        expect(index).toBeUndefined();
        expect(logger.messages('info')).toEqual([
            '  Skipping status bit 4 with empty label_on and label_off',
        ]);
        expect(databoxClient.createEntityList).not.toHaveBeenCalled();
        expect(databoxClient.createAttributeDefinition).not.toHaveBeenCalled();
        expect(databoxClient.createAttributeEntity).not.toHaveBeenCalled();
    });
});

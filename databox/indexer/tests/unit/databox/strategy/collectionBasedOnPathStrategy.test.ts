import {collectionBasedOnPathStrategy} from '../../../../src/databox/strategy/collectionBasedOnPathStrategy';
import {Asset} from '../../../../src/indexers';
import {getLocation} from '../../../../src/locations';
import {
    createFakeDataboxClient,
    FakeDataboxClient,
} from '../../../helpers/databox';
import {createTestLogger, TestLogger} from '../../../helpers/logger';

let client: FakeDataboxClient;
let logger: TestLogger;

const asset = (overrides: Partial<Asset> = {}): Asset => ({
    workspaceId: 'ws-1',
    key: 'fs/a/b/photo.jpg',
    path: 'fs/a/b/photo.jpg',
    publicUrl: 'http://indexer.test/assets/?path=fs%2Fa%2Fb%2Fphoto.jpg',
    isPrivate: true,
    sourcePath: '/source/a/b/photo.jpg',
    ...overrides,
});

beforeEach(() => {
    client = createFakeDataboxClient();
    logger = createTestLogger();
});

describe('collection branch', () => {
    it('creates one collection per path segment but the last', async () => {
        await collectionBasedOnPathStrategy(
            asset(),
            getLocation('fs_test'),
            client,
            logger
        );

        expect(client.createCollectionTreeBranch).toHaveBeenCalledWith(
            'ws-1',
            '',
            [
                {key: 'fs', name: 'fs'},
                {key: 'a', name: 'a'},
                {key: 'b', name: 'b'},
            ]
        );
    });

    it('creates no collection for a file at the root', async () => {
        await collectionBasedOnPathStrategy(
            asset({path: 'photo.jpg', key: 'photo.jpg'}),
            getLocation('fs_test'),
            client,
            logger
        );

        expect(client.createCollectionTreeBranch).toHaveBeenCalledWith(
            'ws-1',
            '',
            []
        );
    });

    it('forwards the collectionKeyPrefix', async () => {
        await collectionBasedOnPathStrategy(
            asset({collectionKeyPrefix: 'phraseanet'}),
            getLocation('fs_test'),
            client,
            logger
        );

        expect(client.createCollectionTreeBranch).toHaveBeenCalledWith(
            'ws-1',
            'phraseanet',
            expect.any(Array)
        );
    });

    it('unescapes escaped slashes inside a segment', async () => {
        await collectionBasedOnPathStrategy(
            asset({path: 'a/b\\/c/photo.jpg'}),
            getLocation('fs_test'),
            client,
            logger
        );

        expect(client.createCollectionTreeBranch).toHaveBeenCalledWith(
            'ws-1',
            '',
            [
                {key: 'a', name: 'a'},
                {key: 'b/c', name: 'b/c'},
            ]
        );
    });

    it('logs and rethrows when the branch cannot be created', async () => {
        client.createCollectionTreeBranch.mockRejectedValue(
            new Error('branch failed')
        );

        await expect(
            collectionBasedOnPathStrategy(
                asset(),
                getLocation('fs_test'),
                client,
                logger
            )
        ).rejects.toThrow('branch failed');

        expect(
            logger.hasMessageMatching('Failed to create collection branch')
        ).toBe(true);
        expect(client.createAsset).not.toHaveBeenCalled();
    });
});

describe('asset creation', () => {
    it('builds the asset payload from the source asset', async () => {
        client.createCollectionTreeBranch.mockResolvedValue('coll-9');

        await collectionBasedOnPathStrategy(
            asset({
                name: 'Custom name',
                generateRenditions: true,
                importFile: true,
                attributes: [{definition: '/d/1', value: 'v'} as any],
                tags: ['/tags/1'],
                renditions: [{name: 'preview'}],
                isStory: false,
            }),
            getLocation('fs_test'),
            client,
            logger
        );

        expect(client.createAsset).toHaveBeenCalledWith({
            workspaceId: 'ws-1',
            sourceFile: {
                url: 'http://indexer.test/assets/?path=fs%2Fa%2Fb%2Fphoto.jpg',
                isPrivate: true,
                alternateUrls: [
                    {type: 'indexer', url: 'indexer:///source/a/b/photo.jpg'},
                ],
                importFile: true,
            },
            collection: '/collections/coll-9',
            generateRenditions: true,
            key: 'fs/a/b/photo.jpg',
            name: 'Custom name',
            attributes: [{definition: '/d/1', value: 'v'}],
            tags: ['/tags/1'],
            renditions: [{name: 'preview'}],
            isStory: false,
        });
    });

    it('falls back to the basename when no name is given', async () => {
        await collectionBasedOnPathStrategy(
            asset(),
            getLocation('fs_test'),
            client,
            logger
        );

        expect(client.createAsset.mock.calls[0][0].name).toEqual('photo.jpg');
    });

    it('omits sourceFile when the asset has no public URL', async () => {
        await collectionBasedOnPathStrategy(
            asset({publicUrl: undefined}),
            getLocation('fs_test'),
            client,
            logger
        );

        expect(client.createAsset.mock.calls[0][0].sourceFile).toBeUndefined();
    });

    it('omits the collection when the branch is empty', async () => {
        client.createCollectionTreeBranch.mockResolvedValue('');

        await collectionBasedOnPathStrategy(
            asset({path: 'photo.jpg'}),
            getLocation('fs_test'),
            client,
            logger
        );

        expect(client.createAsset.mock.calls[0][0].collection).toBeUndefined();
    });

    it('uses the global alternateUrls for a location that declares none', async () => {
        await collectionBasedOnPathStrategy(
            asset(),
            getLocation('s3_test'),
            client,
            logger
        );

        expect(
            client.createAsset.mock.calls[0][0].sourceFile.alternateUrls
        ).toEqual([{type: 'global', url: 'global://fs/a/b/photo.jpg'}]);
    });

    it('logs and rethrows when the asset cannot be created', async () => {
        client.createAsset.mockRejectedValue(new Error('asset failed'));

        await expect(
            collectionBasedOnPathStrategy(
                asset(),
                getLocation('fs_test'),
                client,
                logger
            )
        ).rejects.toThrow('asset failed');

        expect(logger.hasMessageMatching('Failed to create asset')).toBe(true);
    });
});

describe('shortcut collections', () => {
    it('copies the asset by reference into each shortcut collection', async () => {
        client.createAsset.mockResolvedValue({id: 'asset-42'});

        await collectionBasedOnPathStrategy(
            asset({
                shortcutIntoCollections: [
                    {id: 'c1', path: '/classification/a'},
                    {id: 'c2', path: '/classification/b'},
                ],
            }),
            getLocation('fs_test'),
            client,
            logger
        );

        expect(client.copyAsset).toHaveBeenCalledTimes(2);
        expect(client.copyAsset).toHaveBeenNthCalledWith(1, {
            destination: '/collections/c1',
            ids: ['asset-42'],
            byReference: true,
            withAttributes: false,
            withTags: false,
        });
        expect(client.copyAsset).toHaveBeenNthCalledWith(2, {
            destination: '/collections/c2',
            ids: ['asset-42'],
            byReference: true,
            withAttributes: false,
            withTags: false,
        });
    });

    it('copies nothing when there is no shortcut', async () => {
        await collectionBasedOnPathStrategy(
            asset(),
            getLocation('fs_test'),
            client,
            logger
        );

        expect(client.copyAsset).not.toHaveBeenCalled();
    });

    it('logs and rethrows when a copy fails', async () => {
        client.copyAsset.mockRejectedValue(new Error('copy failed'));

        await expect(
            collectionBasedOnPathStrategy(
                asset({
                    shortcutIntoCollections: [{id: 'c1', path: '/x'}],
                }),
                getLocation('fs_test'),
                client,
                logger
            )
        ).rejects.toThrow('copy failed');

        expect(logger.hasMessageMatching('Failed to create asset')).toBe(true);
    });
});

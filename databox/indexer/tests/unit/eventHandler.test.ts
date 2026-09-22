import {handleDeleteObject, handlePutObject} from '../../src/eventHandler';
import {Asset} from '../../src/indexers';
import {getLocation} from '../../src/locations';
import {createFakeDataboxClient} from '../helpers/databox';
import {createTestLogger} from '../helpers/logger';

const asset = (path: string): Asset => ({
    workspaceId: 'ws-1',
    key: path,
    path,
    publicUrl: 'http://indexer.test/x',
    isPrivate: true,
});

describe('handlePutObject', () => {
    it('indexes an asset that passes the filters', async () => {
        const client = createFakeDataboxClient();

        await handlePutObject(
            asset('a/b.jpg'),
            getLocation('fs_test'),
            client,
            createTestLogger()
        );

        expect(client.createAsset).toHaveBeenCalledTimes(1);
    });

    it('skips an asset rejected by the filters', async () => {
        const client = createFakeDataboxClient();

        await handlePutObject(
            asset('a/.hidden.txt'),
            getLocation('fs_test'),
            client,
            createTestLogger()
        );

        expect(client.createAsset).not.toHaveBeenCalled();
        expect(client.createCollectionTreeBranch).not.toHaveBeenCalled();
    });

    it('rethrows an indexation error', async () => {
        const client = createFakeDataboxClient();
        client.createAsset.mockRejectedValue(new Error('boom'));

        await expect(
            handlePutObject(
                asset('a/b.jpg'),
                getLocation('fs_test'),
                client,
                createTestLogger()
            )
        ).rejects.toThrow('boom');
    });
});

describe('handleDeleteObject', () => {
    it('deletes by workspace and key', async () => {
        const client = createFakeDataboxClient();

        await handleDeleteObject(asset('a/b.jpg'), client, createTestLogger());

        expect(client.deleteAsset).toHaveBeenCalledWith('ws-1', 'a/b.jpg');
    });

    it('does not apply the path filters', async () => {
        const client = createFakeDataboxClient();

        await handleDeleteObject(
            asset('a/.hidden.txt'),
            client,
            createTestLogger()
        );

        expect(client.deleteAsset).toHaveBeenCalledWith(
            'ws-1',
            'a/.hidden.txt'
        );
    });

    it('rethrows a deletion error', async () => {
        const client = createFakeDataboxClient();
        client.deleteAsset.mockRejectedValue(new Error('boom'));

        await expect(
            handleDeleteObject(asset('a/b.jpg'), client, createTestLogger())
        ).rejects.toThrow('boom');
    });
});

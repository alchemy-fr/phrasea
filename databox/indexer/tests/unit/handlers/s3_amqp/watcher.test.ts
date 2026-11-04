const holder = vi.hoisted(() => ({
    listenArgs: undefined as any,
    callback: undefined as ((event: string) => Promise<void>) | undefined,
}));

vi.mock('../../../../src/amqp', () => ({
    listenToQueue: vi.fn(
        (
            dsn: string,
            queueName: string,
            callback: (event: string) => Promise<void>,
            logger: any,
            concurrency: number
        ) => {
            holder.listenArgs = {dsn, queueName, logger, concurrency};
            holder.callback = callback;
        }
    ),
}));

import {s3AmqpWatcher} from '../../../../src/handlers/s3_amqp/watcher';
import {IndexLocation} from '../../../../src/types/config';
import {S3AmqpConfig} from '../../../../src/handlers/s3_amqp/types';
import {S3Event, S3Record} from '../../../../src/types/event';
import {
    createFakeDataboxClient,
    FakeDataboxClient,
} from '../../../helpers/databox';
import {createTestLogger} from '../../../helpers/logger';

let client: FakeDataboxClient;

const location = (bucketNames: string | undefined = 'bucket-a,bucket-b') =>
    ({
        name: 's3_test',
        type: 's3_amqp',
        options: {
            workspaceSlug: 'test-workspace',
            amqp: {dsn: 'amqp://localhost/s3events'},
            s3: {
                endpoint: 'http://minio:9000',
                bucketNames,
                accessKey: 'access',
                secretKey: 'secret',
            },
        },
    }) as IndexLocation<S3AmqpConfig>;

const record = (bucket: string, key: string): S3Record =>
    ({
        s3: {
            bucket: {name: bucket},
            object: {key},
        },
    }) as S3Record;

const event = (
    EventName: string,
    records: S3Record[] = [record('bucket-a', 'a/b.jpg')]
): string => JSON.stringify({EventName, Key: '', Records: records} as S3Event);

const start = async (loc = location()) => {
    await s3AmqpWatcher(loc, client, createTestLogger());

    return holder.callback!;
};

beforeEach(() => {
    client = createFakeDataboxClient();
    holder.listenArgs = undefined;
    holder.callback = undefined;
});

describe('queue subscription', () => {
    it('subscribes to the s3events queue with the configured DSN', async () => {
        await start();

        expect(holder.listenArgs).toMatchObject({
            dsn: 'amqp://localhost/s3events',
            queueName: 's3events',
        });
    });

    it('resolves the workspace once, up front', async () => {
        await start();

        expect(client.getWorkspaceIdFromSlug).toHaveBeenCalledWith(
            'test-workspace'
        );
    });

    it('takes the concurrency from the global config', async () => {
        await start();

        expect(holder.listenArgs.concurrency).toEqual(2);
    });
});

describe('object created events', () => {
    it.each([
        's3:ObjectCreated:Put',
        's3:ObjectCreated:Post',
        's3:ObjectCreated:CompleteMultipartUpload',
        's3:ObjectCreated:Copy',
    ])('indexes the object on %s', async eventName => {
        const onEvent = await start();

        await onEvent(event(eventName));

        expect(client.createAsset).toHaveBeenCalledTimes(1);
        expect(client.createAsset.mock.calls[0][0]).toMatchObject({
            workspaceId: 'workspace-1',
            key: 'a/b.jpg',
        });
    });

    it('indexes every record of a batched event', async () => {
        const onEvent = await start();

        await onEvent(
            event('s3:ObjectCreated:Put', [
                record('bucket-a', 'a.jpg'),
                record('bucket-b', 'b.jpg'),
            ])
        );

        expect(client.createAsset).toHaveBeenCalledTimes(2);
    });
});

describe('object removed events', () => {
    it('deletes the asset on s3:ObjectRemoved:Delete', async () => {
        const onEvent = await start();

        await onEvent(event('s3:ObjectRemoved:Delete'));

        expect(client.deleteAsset).toHaveBeenCalledWith(
            'workspace-1',
            'a/b.jpg'
        );
        expect(client.createAsset).not.toHaveBeenCalled();
    });
});

describe('ignored events', () => {
    it('does nothing on s3:ObjectAccessed:Get', async () => {
        const onEvent = await start();

        await onEvent(event('s3:ObjectAccessed:Get'));

        expect(client.createAsset).not.toHaveBeenCalled();
        expect(client.deleteAsset).not.toHaveBeenCalled();
    });

    it('does nothing on an unknown event name', async () => {
        const onEvent = await start();

        await onEvent(event('s3:Something:Else'));

        expect(client.createAsset).not.toHaveBeenCalled();
        expect(client.deleteAsset).not.toHaveBeenCalled();
    });
});

describe('key decoding', () => {
    it('turns + into a space and decodes percent escapes', async () => {
        const onEvent = await start();

        await onEvent(
            event('s3:ObjectCreated:Put', [
                record(
                    'bucket-a',
                    'Dossier+Accentu%C3%A9/%C3%89t%C3%A9+2024.txt'
                ),
            ])
        );

        expect(client.createAsset.mock.calls[0][0].key).toEqual(
            'Dossier Accentué/Été 2024.txt'
        );
    });
});

describe('bucket filtering', () => {
    it('ignores a record from a bucket outside the list', async () => {
        const onEvent = await start();

        await onEvent(
            event('s3:ObjectCreated:Put', [record('other', 'a.jpg')])
        );

        expect(client.createAsset).not.toHaveBeenCalled();
    });

    it('accepts every bucket when bucketNames is empty', async () => {
        // ''.split(',') yields [''], whose length is 1, so the guard used to
        // reject every bucket instead of accepting all of them.
        const onEvent = await start(location(''));

        await onEvent(
            event('s3:ObjectCreated:Put', [record('anything', 'a.jpg')])
        );

        expect(client.createAsset).toHaveBeenCalledTimes(1);
    });

    it('ignores the blank entries of a padded bucketNames', async () => {
        const onEvent = await start(location('bucket-a, ,bucket-b'));

        await onEvent(
            event('s3:ObjectCreated:Put', [record('other', 'a.jpg')])
        );
        expect(client.createAsset).not.toHaveBeenCalled();

        await onEvent(
            event('s3:ObjectCreated:Put', [record('bucket-b', 'a.jpg')])
        );
        expect(client.createAsset).toHaveBeenCalledTimes(1);
    });
});

describe('filters', () => {
    it('applies the blacklist to created objects', async () => {
        const onEvent = await start();

        await onEvent(
            event('s3:ObjectCreated:Put', [record('bucket-a', 'a/.hidden.txt')])
        );

        expect(client.createAsset).not.toHaveBeenCalled();
    });
});

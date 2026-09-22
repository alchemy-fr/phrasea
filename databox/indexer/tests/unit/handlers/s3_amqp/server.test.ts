const holder = vi.hoisted(() => ({
    signUriArgs: undefined as any,
}));

vi.mock('../../../../src/s3/s3', () => ({
    createS3Client: vi.fn(() => ({__fake: 's3'})),
    signUri: vi.fn(async (client: any, bucket: string, key: string) => {
        holder.signUriArgs = {client, bucket, key};

        return `https://signed.example.com/${bucket}/${key}`;
    }),
}));

import {s3AmqpAssetServerFactory} from '../../../../src/handlers/s3_amqp/server';
import {IndexLocation} from '../../../../src/types/config';
import {S3AmqpConfig} from '../../../../src/handlers/s3_amqp/types';
import {createFakeResponse} from '../../../helpers/databox';
import {createTestLogger, TestLogger} from '../../../helpers/logger';

let logger: TestLogger;

const location = (bucketNames = 'bucket-a,bucket-b') =>
    ({
        name: 's3_test',
        type: 's3_amqp',
        options: {
            workspaceSlug: 'test-workspace',
            amqp: {dsn: 'amqp://localhost'},
            s3: {
                endpoint: 'http://minio:9000',
                bucketNames,
                accessKey: 'access',
                secretKey: 'secret',
            },
        },
    }) as IndexLocation<S3AmqpConfig>;

beforeEach(() => {
    logger = createTestLogger();
    holder.signUriArgs = undefined;
});

describe('s3AmqpAssetServerFactory', () => {
    it('redirects to a presigned URL for an allowed bucket', async () => {
        const res = createFakeResponse();

        await s3AmqpAssetServerFactory(location(), logger)('a/b.jpg', res, {
            bucket: 'bucket-a',
        });

        expect(res.redirect).toHaveBeenCalledWith(
            307,
            'https://signed.example.com/bucket-a/a/b.jpg'
        );
        expect(holder.signUriArgs).toMatchObject({
            bucket: 'bucket-a',
            key: 'a/b.jpg',
        });
    });

    it('accepts any bucket of the configured list', async () => {
        const res = createFakeResponse();

        await s3AmqpAssetServerFactory(location(), logger)('x', res, {
            bucket: 'bucket-b',
        });

        expect(res.redirect).toHaveBeenCalled();
    });

    it('answers 404 for a bucket outside the list', async () => {
        const res = createFakeResponse();

        await s3AmqpAssetServerFactory(location(), logger)('a/b.jpg', res, {
            bucket: 'other-bucket',
        });

        expect(res.status).toHaveBeenCalledWith(404);
        expect(res.body).toEqual({
            error: 'Not Found',
            error_description: 'Invalid bucket',
        });
        expect(res.redirect).not.toHaveBeenCalled();
    });

    it('answers 404 when no bucket is given', async () => {
        const res = createFakeResponse();

        await s3AmqpAssetServerFactory(location(), logger)('a/b.jpg', res, {});

        expect(res.status).toHaveBeenCalledWith(404);
    });
});

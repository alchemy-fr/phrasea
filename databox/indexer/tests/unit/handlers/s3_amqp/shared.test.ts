import {
    createAsset,
    createS3ClientFromConfig,
} from '../../../../src/handlers/s3_amqp/shared';
import {S3AmqpConfig} from '../../../../src/handlers/s3_amqp/types';

const s3Config = (endpoint: string): S3AmqpConfig =>
    ({
        workspaceSlug: 'test-workspace',
        amqp: {dsn: 'amqp://localhost'},
        s3: {
            endpoint,
            bucketNames: 'bucket-a',
            accessKey: 'access',
            secretKey: 'secret',
        },
    }) as S3AmqpConfig;

describe('createAsset', () => {
    it('keys the asset on the object path and carries the bucket in the URL', () => {
        expect(createAsset('ws-1', 'a/b.jpg', 's3_test', 'bucket-a')).toEqual({
            workspaceId: 'ws-1',
            key: 'a/b.jpg',
            path: 'a/b.jpg',
            isPrivate: true,
            publicUrl:
                'http://indexer.test/assets/?bucket=bucket-a&path=a%2Fb.jpg&source=s3_test',
            sourcePath: 'a/b.jpg',
        });
    });

    it('uses the raw path as sourcePath', () => {
        const asset = createAsset(
            'ws-1',
            'Dossier Accentué/x.jpg',
            's3_test',
            'bucket-a'
        );

        expect(asset.sourcePath).toEqual('Dossier Accentué/x.jpg');
        expect(asset.publicUrl).toContain('path=Dossier+Accentu%C3%A9%2Fx.jpg');
    });
});

describe('createS3ClientFromConfig', () => {
    it('enables SSL for an https endpoint', () => {
        const client: any = createS3ClientFromConfig(
            s3Config('https://minio.example.com')
        );

        expect(client.host).toEqual('minio.example.com');
        expect(client.protocol).toEqual('https:');
        expect(client.port).toEqual(443);
    });

    it('disables SSL for an http endpoint', () => {
        const client: any = createS3ClientFromConfig(
            s3Config('http://minio.example.com')
        );

        expect(client.protocol).toEqual('http:');
        expect(client.port).toEqual(80);
    });

    it('honours an explicit port', () => {
        const client: any = createS3ClientFromConfig(
            s3Config('http://minio:9000')
        );

        expect(client.host).toEqual('minio');
        expect(client.port).toEqual(9000);
    });

    it('carries the credentials', () => {
        const client: any = createS3ClientFromConfig(
            s3Config('http://minio:9000')
        );

        expect(client.accessKey).toEqual('access');
        expect(client.secretKey).toEqual('secret');
    });
});

import {IndexIterator} from '../../indexers';
import {createAsset, createS3ClientFromConfig} from './shared';
import {S3AmqpConfig} from './types';
import {streamify} from '../../lib/streamify';
import {getStrict} from '../../configLoader';

export const s3AmqpIterator: IndexIterator<S3AmqpConfig> = async function* (
    location,
    logger,
    databoxClient
) {
    const config = location.options;
    const s3Client = createS3ClientFromConfig(config);
    const workspaceId = await databoxClient.getWorkspaceIdFromSlug(
        getStrict('workspaceSlug', config)
    );

    const buckets = config.s3.bucketNames.split(',');

    for (const bucket of buckets) {
        logger.info(`Start Indexing S3 bucket "${bucket}"`);

        const stream = s3Client.listObjectsV2(bucket, '', true, '');

        for await (const path of streamify(stream, 'data', 'end')) {
            yield createAsset(workspaceId, path, location.name, bucket);
        }
    }
};

import {collectionBasedOnPathStrategy} from "../../databox/strategy/collectionBasedOnPathStrategy";
import {generatePublicUrl} from "../../resourceResolver";
import {Indexer} from "../../indexers";
import {createS3ClientFromConfig} from "./shared";
import {S3AmqpConfig} from "./types";

export const s3AmqpIndexer: Indexer<S3AmqpConfig> = async (
    location,
    databoxClient,
    logger,
    onProgress
) => {
    const concurrency = 2;
    const bufferSize = 5000;
    const buffer: string[] = [];
    let total = 0;

    async function flush() {
        while (buffer.length > 0) {
            const promises: Promise<void>[] = [];
            for (let i = 0; i < concurrency; ++i) {
                const path = buffer.shift();
                logger.info(`Indexing asset "${path}"`);
                promises.push(collectionBasedOnPathStrategy(
                    generatePublicUrl(path, location.name),
                    databoxClient,
                    path,
                    logger
                ));
            }

            await Promise.all(promises);

            total += concurrency;
            onProgress(total, undefined);
        }
    }

    const config = location.options;
    const s3Client = createS3ClientFromConfig(config);
    const stream = s3Client.listObjectsV2(config.s3.bucketNames, '', true, '');

    stream.on('data', async (obj) => {
        buffer.push(obj.name);
        if (buffer.length >= bufferSize) {
            stream.pause();
            await flush();
            stream.resume();
        }
    });
    stream.on('end', () => {
        flush();
    });
    stream.on('error', (error) => {
        throw error;
    })

}

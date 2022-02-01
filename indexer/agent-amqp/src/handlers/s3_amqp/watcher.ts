import {listenToQueue} from "../../amqp";
import {getConfig, getStrict} from "../../configLoader";
import {declareAssetServer} from "../../server";
import {signUri} from "../../s3/s3";
import {S3Event} from "../../types/event";
import {handleDeleteObject, handlePutObject} from "../../eventHandler";
import {generatePublicUrl} from "../../resourceResolver";
import {createS3ClientFromConfig} from "./shared";
import {S3AmqpConfig} from "./types";
import {Watcher} from "../../watchers";

export const s3AmqpWatcher: Watcher<S3AmqpConfig> = (
    location,
    databoxClient,
    logger) => {
    const config = location.options as S3AmqpConfig;

    const bucketsList: string[] = (getConfig('s3.bucketNames', '', config)).split(',');

    listenToQueue(
        getStrict('amqp.dsn', config),
        's3events',
        async (event) => {
            const {
                EventName,
                Records
            } = JSON.parse(event) as S3Event;

            logger.debug('event', {
                event,
            });

            await Promise.all(Records.map(r => {
                if (bucketsList.length > 0 && !bucketsList.includes(r.s3.bucket.name)) {
                    return;
                }

                const path = decodeURIComponent(r.s3.object.key.replace(/\+/g, '%20'));
                console.debug(EventName, path);

                switch (EventName) {
                    case 's3:ObjectCreated:Put':
                    case 's3:ObjectCreated:Post':
                    case 's3:ObjectCreated:CompleteMultipartUpload':
                    case 's3:ObjectCreated:Copy':
                        return handlePutObject(generatePublicUrl(path, location.name, {
                            bucket: r.s3.bucket.name,
                        }), path, databoxClient, logger);
                    case 's3:ObjectRemoved:Delete':
                        return handleDeleteObject(path, databoxClient, logger);
                    case 's3:ObjectAccessed:Get':
                        return;
                }
            }));
        },
        logger
    );

    const s3Client = createS3ClientFromConfig(config);

    declareAssetServer(location.name, async (path, res, query) => {
        res.redirect(307, await signUri(s3Client, query.bucket, path));
    });
}

import {listenToQueue} from '../../amqp';
import {getConfig, getStrict} from '../../configLoader';
import {S3Event} from '../../types/event';
import {handleDeleteObject, handlePutObject} from '../../eventHandler';
import {S3AmqpConfig} from './types';
import {Watcher} from '../../watchers';
import {Asset} from '../../indexers';
import {createAsset} from './shared';

export const s3AmqpWatcher: Watcher<S3AmqpConfig> = async (
    location,
    databoxClient,
    logger
) => {
    const config = location.options as S3AmqpConfig;

    const bucketsList: string[] = getConfig('s3.bucketNames', '', config).split(
        ','
    );

    const workspaceId = await databoxClient.getWorkspaceIdFromSlug(
        getStrict('workspaceSlug', config)
    );

    const concurrency = getConfig('databox.concurrency', 1);

    listenToQueue(
        getStrict('amqp.dsn', config),
        's3events',
        async event => {
            const {EventName, Records} = JSON.parse(event) as S3Event;

            logger.debug('event', {
                event,
            });

            await Promise.all(
                Records.map(r => {
                    if (
                        bucketsList.length > 0 &&
                        !bucketsList.includes(r.s3.bucket.name)
                    ) {
                        return;
                    }

                    const path = decodeURIComponent(
                        r.s3.object.key.replace(/\+/g, '%20')
                    );
                    const asset: Asset = createAsset(
                        workspaceId,
                        path,
                        location.name,
                        r.s3.bucket.name
                    );

                    switch (EventName) {
                        case 's3:ObjectCreated:Put':
                        case 's3:ObjectCreated:Post':
                        case 's3:ObjectCreated:CompleteMultipartUpload':
                        case 's3:ObjectCreated:Copy':
                            return handlePutObject(
                                asset,
                                location,
                                databoxClient,
                                logger
                            );
                        case 's3:ObjectRemoved:Delete':
                            return handleDeleteObject(
                                asset,
                                databoxClient,
                                logger
                            );
                        case 's3:ObjectAccessed:Get':
                            return;
                    }
                })
            );
        },
        logger,
        concurrency
    );
};

import {listenToQueue} from "../amqp";
import {IndexLocation} from "../types/config";
import {getConfig, getStrict} from "../configLoader";
import {declareAssetServer} from "../server";
import {createS3Client, signUri} from "../s3/s3";
import {DataboxClient} from "../lib/databox/client";
import url from "url";
import {S3Event} from "../types/event";
import {handleDeleteObject, handlePutObject} from "../listener/eventHandler";
import {generatePublicUrl} from "../resourceResolver";

export function s3AmqpHandler(location: IndexLocation, databoxClient: DataboxClient) {
    const config = location.options || {};

    const bucketsList: string[] = (getConfig('s3.bucketNames', '', config)).split(',');

    listenToQueue(
        getStrict('amqp.dsn', config),
        's3events',
        async (event) => {
            const {
                EventName,
                Records
            } = JSON.parse(event) as S3Event;

            console.log('event', JSON.stringify(JSON.parse(event), null, 2));

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
                        }), path, databoxClient);
                    case 's3:ObjectRemoved:Delete':
                        return handleDeleteObject(path, databoxClient);
                    case 's3:ObjectAccessed:Get':
                        return;
                }
            }));
        },
    );

    const {
        hostname,
        port,
        protocol,
    } = url.parse(getStrict('s3.endpoint', config));

    console.log('hostname', hostname);

    const s3Client = createS3Client({
        type: 's3',
        useSSL: protocol === 'https:',
        insecure: true,
        endPoint: hostname,
        port: port ? parseInt(port) : undefined,
        accessKey: getStrict('s3.accessKey', config),
        secretKey: getStrict('s3.secretKey', config),
    })

    declareAssetServer(location.name, async (path, res, query) => {
        res.redirect(307, await signUri(s3Client, query.bucket, path));
    });
}

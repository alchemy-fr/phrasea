import {DataboxClient} from "../lib/databox/client";
import {createS3Client} from "../s3/s3";
import {collectionBasedOnPathStrategy} from "../lib/databox/strategy/collectionBasedOnPathStrategy";
import {castToBoolean, getConfig, getStrict} from "../configLoader";

const databoxClient = new DataboxClient({
    apiUrl: getStrict('databox.url'),
    clientId: getStrict('databox.clientId'),
    clientSecret: getStrict('databox.clientSecret'),
    workspaceId: getStrict('databox.workspaceId'),
    collectionId: getStrict('databox.clientSecret'),
    ownerId: getStrict('databox.ownerId'),
    verifySSL: castToBoolean(getConfig('databox.verifySSL', true)),
    scope: 'chuck-norris'
});

const s3Client = createS3Client('s3main');

console.info(`Indexing bucket "${sources.s3main.bucketName}"...`);

const stream = s3Client.listObjectsV2(sources.s3main.bucketName, '', true, '');

const concurrency = 2;
const bufferSize = 5000;
const buffer: string[] = [];

async function flush() {
    while (buffer.length > 0) {
        const promises: Promise<void>[] = [];
        for (let i = 0; i < concurrency; ++i) {
            const path = buffer.shift();
            console.info(`Indexing asset "${path}"`);
            promises.push(collectionBasedOnPathStrategy(databoxClient, path));
        }

        await Promise.all(promises);
    }
}

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

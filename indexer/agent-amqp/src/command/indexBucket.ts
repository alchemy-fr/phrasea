import {castEnvToBoolean, getEnv, getEnvStrict} from "../env";
import {DataboxClient} from "../lib/databox/client";
import {sources} from "../sources";
import {createS3Client} from "../s3";
import {collectionBasedOnPathStrategy} from "../lib/databox/strategy/collectionBasedOnPathStrategy";

const databoxClient = new DataboxClient({
    apiUrl: getEnvStrict('DATABOX_API_URL'),
    clientId: getEnvStrict('DATABOX_CLIENT_ID'),
    clientSecret: getEnvStrict('DATABOX_CLIENT_SECRET'),
    workspaceId: getEnvStrict('DATABOX_WORKSPACE_ID'),
    collectionId: getEnv('DATABOX_COLLECTION_ID'),
    ownerId: getEnvStrict('DATABOX_OWNER_ID'),
    verifySSL: castEnvToBoolean(getEnv('DATABOX_VERIFY_SSL')),
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

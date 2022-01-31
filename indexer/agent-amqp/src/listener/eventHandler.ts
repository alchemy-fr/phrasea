import {S3Event} from "../types/event";
import {DataboxClient} from "../lib/databox/client";
import {generatePublicUrl} from "../resourceResolver";
import p from 'path';
import {getEnv} from "../env";
import {config} from "../configLoader";
import {getAlternateUrls} from "../alternateUrl";
import {collectionBasedOnPathStrategy} from "../lib/databox/strategy/collectionBasedOnPathStrategy";

const bucketsList: string[] = getEnv('BUCKET_NAMES', '').split(',');

export async function handleEvent(event: string, databoxClient: DataboxClient) {
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
                return handlePutObject(path, databoxClient);
            case 's3:ObjectRemoved:Delete':
                return handleDeleteObject(path, databoxClient);
            case 's3:ObjectCreated:Copy':
                return handleMoveObject(path, databoxClient);
            case 's3:ObjectAccessed:Get':
                return;
        }
    }));
}

async function handlePutObject(path: string, databoxClient: DataboxClient) {
    try {
        await collectionBasedOnPathStrategy(databoxClient, path);
    } catch (error) {
        if (error.response) {
            console.error(error.response.data);
        }

        throw error;
    }
}

async function handleDeleteObject(path: string, databoxClient: DataboxClient) {
    console.log('handleDeleteObject', path);

    try {
        await databoxClient.deleteAsset(path);
    } catch (error) {
        if (error.response) {
            console.error(error.response.data);
        }

        throw error;
    }
}

async function handleMoveObject(path: string, databoxClient: DataboxClient) {
    console.log('handleMoveObject', path);
}

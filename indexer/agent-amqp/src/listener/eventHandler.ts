import {S3Event} from "../types/event";
import {DataboxClient} from "../databox/client";
import {generatePublicUrl} from "../resourceResolver";
import p from 'path';
import {getEnv} from "../env";

const bucketsList: string[] = getEnv('BUCKET_NAMES', '').split(',');

export async function handleEvent(event: string, databoxClient: DataboxClient) {
    const {
        EventName,
        Records
    } = JSON.parse(event) as S3Event;

    await Promise.all(Records.map(r => {
        const path = r.s3.object.key;

        console.log('r.s3.bucket.name', r.s3.bucket.name);

        if (bucketsList.length > 0 && !bucketsList.includes(r.s3.bucket.name)) {
            return;
        }

        console.debug(EventName, path);

        switch (EventName) {
            case 's3:ObjectCreated:Put':
                return handlePutObject(path, databoxClient);
            case 's3:ObjectAccessed:Get':
                return;
        }
    }));
}

async function handlePutObject(path: string, databoxClient: DataboxClient) {
    try {
        await databoxClient.postAsset({
            source: generatePublicUrl(path),
            key: path,
            title: p.basename(path),
        });
    } catch (error) {
        if (error.response) {
            console.error(error.response.data);
        }

        throw error;
    }
}

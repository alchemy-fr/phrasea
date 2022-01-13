import {S3Event} from "../types/event";
import {DataboxClient} from "../databox/client";
import {generatePublicUrl} from "../resourceResolver";
import p from 'path';

export async function handleEvent(event: string, databoxClient: DataboxClient) {
    console.log('event', event);

    const {
        EventName,
        Records
    } = JSON.parse(event) as S3Event;

    await Promise.all(Records.map(r => {
        const path = r.s3.object.key;

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

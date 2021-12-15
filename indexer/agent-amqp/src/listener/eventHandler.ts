import {S3Event} from "../types/event";
import {DataboxClient} from "../databox/client";
import {generatePublicUrl} from "../resourceResolver";

export async function handleEvent(event: string, databoxClient: DataboxClient) {
    console.log('event', event);

    const {
        EventName,
        Records
    } = JSON.parse(event) as S3Event;

    Records.forEach(r => {
        const path = r.s3.object.key;

        switch (EventName) {
            case 's3:ObjectCreated:Put':
                handlePutObject(path, databoxClient);
                break;
        }
    });
}

async function handlePutObject(path: string, databoxClient: DataboxClient) {
    await databoxClient.postAsset({
        source: generatePublicUrl(path),
        key: path,
    });
}

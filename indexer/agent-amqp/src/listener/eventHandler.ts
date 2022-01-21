import {S3Event} from "../types/event";
import {DataboxClient} from "../databox/client";
import {generatePublicUrl} from "../resourceResolver";
import p from 'path';
import {getEnv} from "../env";
import {config} from "../configLoader";

const bucketsList: string[] = getEnv('BUCKET_NAMES', '').split(',');

export async function handleEvent(event: string, databoxClient: DataboxClient) {
    const {
        EventName,
        Records
    } = JSON.parse(event) as S3Event;

    await Promise.all(Records.map(r => {
        const path = decodeURIComponent(r.s3.object.key);

        if (bucketsList.length > 0 && !bucketsList.includes(r.s3.bucket.name)) {
            return;
        }

        console.debug(EventName, path);

        switch (EventName) {
            case 's3:ObjectCreated:Put':
                return handlePutObject(path, databoxClient);
            case 's3:ObjectRemoved:Delete':
                return handleDeleteObject(path, databoxClient);
            case 's3:ObjectAccessed:Get':
                return;
        }
    }));
}

async function handlePutObject(path: string, databoxClient: DataboxClient) {
    try {
        let alternateUrls;

        if (config.alternateUrls) {
            const dict = {
                path,
            };
            alternateUrls = config.alternateUrls.map(c => {
               return {
                   type: c.name,
                   url: c.pathPattern.replace(/\${(.+)}/g, (m) => {
                       return dict[m];
                   }),
               }
            });
        }

        let branch = path.split('/');
        branch.pop();

        const collIRI = await databoxClient.createCollectionTreeBranch(branch.map(k => ({
            key: k,
            title: k
        })))

        await databoxClient.createAsset({
            source: {
                url: generatePublicUrl(path),
                isPrivate: true,
                alternateUrls,
            },
            collection: collIRI,
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

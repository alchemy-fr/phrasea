import {DataboxClient} from "../lib/databox/client";
import {collectionBasedOnPathStrategy} from "../lib/databox/strategy/collectionBasedOnPathStrategy";

export async function handlePutObject(publicUrl: string, path: string, databoxClient: DataboxClient) {
    try {
        await collectionBasedOnPathStrategy(publicUrl, databoxClient, path);
    } catch (error) {
        if (error.response) {
            console.error(error.response.data);
        }

        throw error;
    }
}

export async function handleDeleteObject(path: string, databoxClient: DataboxClient) {
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

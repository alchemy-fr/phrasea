import {DataboxClient} from "./databox/client";
import {collectionBasedOnPathStrategy} from "./databox/strategy/collectionBasedOnPathStrategy";
import {Logger} from "winston";

export async function handlePutObject(publicUrl: string, path: string, databoxClient: DataboxClient, logger: Logger) {
    try {
        await collectionBasedOnPathStrategy(publicUrl, databoxClient, path, logger);
    } catch (error) {
        if (error.response) {
            console.error(error.response.data);
        }

        throw error;
    }
}

export async function handleDeleteObject(path: string, databoxClient: DataboxClient, logger: Logger) {
    try {
        await databoxClient.deleteAsset(path);
    } catch (error) {
        if (error.response) {
            console.error(error.response.data);
        }

        throw error;
    }
}

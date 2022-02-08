import {DataboxClient} from "./databox/client";
import {collectionBasedOnPathStrategy} from "./databox/strategy/collectionBasedOnPathStrategy";
import {Logger} from "winston";
import {passFilters} from "./pathFilter";
import {IndexLocation} from "./types/config";
import {Asset} from "./indexers";

export async function handlePutObject(asset: Asset, location: IndexLocation<any>, databoxClient: DataboxClient, logger: Logger) {
    if (!passFilters(asset, logger)) {
        return;
    }

    try {
        await collectionBasedOnPathStrategy(asset, location, databoxClient, logger);
    } catch (error) {
        if (error.response) {
            console.error(error.response.data);
        }

        throw error;
    }
}

export async function handleDeleteObject(asset: Asset, databoxClient: DataboxClient, logger: Logger) {
    try {
        await databoxClient.deleteAsset(asset.path);
    } catch (error) {
        if (error.response) {
            console.error(error.response.data);
        }

        throw error;
    }
}

import {parralelize} from "../lib/parralelize";
import {collectionBasedOnPathStrategy} from "./strategy/collectionBasedOnPathStrategy";
import {Logger} from "winston";
import {Asset} from "../indexers";
import {DataboxClient} from "./client";
import {getConfig} from "../configLoader";
import {passFilters} from "../pathFilter";

export async function consume(databoxClient: DataboxClient, iterator: AsyncGenerator<Asset>, logger: Logger) {
    let total = 1;

    const concurrency = getConfig('databox.concurrency', 1);

    await parralelize<Asset>(() => iterator, async (asset) => {
        if (!passFilters(asset.path, logger)) {
            return;
        }

        logger.info(`Indexing asset "${asset.path}"`);

        await collectionBasedOnPathStrategy(
            asset.publicUrl,
            databoxClient,
            asset.path,
            logger
        );

        logger.debug(`${total++} indexed`);
    }, concurrency);
}

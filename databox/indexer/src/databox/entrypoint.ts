import {parralelize} from "./parralelize";
import {collectionBasedOnPathStrategy} from "./strategy/collectionBasedOnPathStrategy";
import {Logger} from "winston";
import {Asset} from "../indexers";
import {DataboxClient} from "./client";
import {getConfig} from "../configLoader";

export async function consume(databoxClient: DataboxClient, iterator: AsyncGenerator<Asset>, logger: Logger) {
    let total = 1;

    const whitelist: string[] | null = getConfig('whitelist', null);
    const blacklist: string[] | null = getConfig('blacklist', null);
    const concurrency = getConfig('databox.concurrency', 1);

    await parralelize<Asset>(() => iterator, async (asset) => {
        if (whitelist && !whitelist.some(w => asset.path.match(new RegExp(w)))) {
            logger.debug(`"${asset.path}" does not match whitelist, skipping...`);
            return;
        }
        if (blacklist && blacklist.some(w => asset.path.match(new RegExp(w)))) {
            logger.debug(`"${asset.path}" does not match blacklist, skipping...`);
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

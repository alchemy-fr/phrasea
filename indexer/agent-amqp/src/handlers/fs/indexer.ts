import {Indexer} from "../../indexers";
import {scanRecursiveDir} from "./shared";
import {FsConfig} from "./types";
import {collectionBasedOnPathStrategy} from "../../databox/strategy/collectionBasedOnPathStrategy";
import {generatePublicUrl} from "../../resourceResolver";

export const fsIndexer: Indexer<FsConfig> = async (
    location,
    databoxClient,
    logger,
    onProgress) => {

    const concurrency = 1;
    const bufferSize = 5000;
    const buffer: string[] = [];
    let total = 0;

    async function flush() {
        while (buffer.length > 0) {
            const promises: Promise<void>[] = [];
            for (let i = 0; i < concurrency; ++i) {
                const path = buffer.shift();
                logger.info(`Indexing asset "${path}"`);
                promises.push(collectionBasedOnPathStrategy(
                    generatePublicUrl(path, location.name),
                    databoxClient,
                    path,
                    logger
                ));
            }

            await Promise.all(promises);

            total += concurrency;
            onProgress(total, undefined);
        }
    }

    logger.info(`Scanning directory "${location.options.dir}"`);
    for (const f of await scanRecursiveDir(location.options.dir)) {
        buffer.push(f);
        if (buffer.length >= bufferSize) {
            await flush();
        }
    }
    await flush();
}

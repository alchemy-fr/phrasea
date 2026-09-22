import {parralelize} from '../lib/parralelize';
import {collectionBasedOnPathStrategy} from './strategy/collectionBasedOnPathStrategy';
import {Logger} from 'winston';
import {Asset} from '../indexers';
import {DataboxClient} from './client';
import {getConfig} from '../configLoader';
import {passFilters} from '../pathFilter';
import {IndexLocation} from '../types/config';
import {isShutdownRequested, shutdownExitCode} from '../shutdown';

export async function consume(
    location: IndexLocation<any>,
    databoxClient: DataboxClient,
    iterator: AsyncGenerator<Asset>,
    logger: Logger
) {
    let total = 1;

    const concurrency = getConfig('databox.concurrency', 1);

    await parralelize<Asset>(
        () => iterator,
        async asset => {
            if (!passFilters(asset, logger)) {
                return;
            }

            logger.info(`Indexing asset "${asset.path}"`);

            await collectionBasedOnPathStrategy(
                asset,
                location,
                databoxClient,
                logger
            );

            logger.debug(`${total++} indexed`);
        },
        concurrency
    );

    if (isShutdownRequested()) {
        logger.warn(`Interrupted after ${total - 1} assets.`);
        process.exit(shutdownExitCode());
    }
}

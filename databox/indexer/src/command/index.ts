import {createDataboxClientFromConfig} from "../databox/client";
import {createLogger} from "../lib/logger";
import {indexers} from "../indexers";
import {getLocation} from "../locations";

const locationName = process.argv[2] || undefined;
if (!locationName) {
    throw new Error(`Missing argument 1: location-name`);
}

const location = getLocation(locationName);

(async () => {
    const databoxLogger = createLogger('databox');
    const databoxClient = createDataboxClientFromConfig(databoxLogger);
    const mainLogger = createLogger('app');
    mainLogger.info(`Indexing "${location.name}"...`);

    const indexer = indexers[location.type];

    await indexer(location, databoxClient, createLogger(location.name), (i, total) => {
        mainLogger.debug(`${i}${total ? `/${total}` : ''} Indexed`);
    });
})();


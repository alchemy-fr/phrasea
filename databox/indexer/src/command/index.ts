import {createDataboxClientFromConfig} from "../databox/client";
import {createLogger} from "../lib/logger";
import {indexers} from "../indexers";
import {getLocation} from "../locations";
import {consume} from "../databox/entrypoint";

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

    await databoxClient.authenticate();

    const indexer = indexers[location.type];

    const logger = createLogger(location.name);
    const iterator = indexer(location, logger);

    await consume(databoxClient, iterator, logger);
})();


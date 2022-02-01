import {createDataboxClientFromConfig} from "../databox/client";
import {createLogger} from "../lib/logger";
import {IndexLocation} from "../types/config";
import {getConfig} from "../configLoader";
import {indexers} from "../indexers";

const locationName = process.argv[2] || undefined;
if (!locationName) {
    throw new Error(`Missing argument 1: location-name`);
}

const locations: IndexLocation<any>[] = getConfig('locations');

const location: IndexLocation<any> | undefined = locations.find(l => l.name === locationName);
if (!location) {
    throw new Error(`Unknown location ${locationName}`);
}

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


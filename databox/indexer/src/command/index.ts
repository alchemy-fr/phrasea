import {createDataboxClientFromConfig} from "../databox/client";
import {createLogger} from "../lib/logger";
import {indexers} from "../indexers";
import {getLocation} from "../locations";
import {consume} from "../databox/entrypoint";

export type IndexOptions = {
    createNewWorkspace?: boolean;
}

export default async function indexCommand(locationName: string, options: IndexOptions) {
    const location = getLocation(locationName);

    const databoxLogger = createLogger('databox');
    const databoxClient = createDataboxClientFromConfig(databoxLogger);
    const mainLogger = createLogger('app');
    mainLogger.info(`Indexing "${location.name}"...`);

    await databoxClient.authenticate();

    const indexer = indexers[location.type];

    const logger = createLogger(location.name);
    const iterator = indexer(location, logger, databoxClient, options);

    await consume(location, databoxClient, iterator, logger);
};


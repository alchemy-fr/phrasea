import {createDataboxClientFromConfig} from '../databox/client.js';
import {createLogger} from '../lib/logger.js';
import {indexers} from '../indexers.js';
import {getLocations} from '../locations.js';
import {consume} from '../databox/entrypoint.js';
import {runServer} from '../server';
import {CommandCommonOptions} from "../types";
import {applyCommonOptions} from "./commandUtil";

export type IndexOptions = {
    createNewWorkspace?: boolean;
} & CommandCommonOptions;

export default async function indexAllCommand(
    options: IndexOptions
) {
    applyCommonOptions(options);
    const locations = getLocations();

    const databoxLogger = createLogger('databox');
    const databoxClient = createDataboxClientFromConfig(databoxLogger);

    await databoxClient.authenticate();
    const mainLogger = createLogger('app');
    runServer(mainLogger);

    const entries = Object.entries(locations);
    for (const [_, location] of entries) {
        mainLogger.info(`Indexing "${location.name}"...`);
        const indexer = indexers[location.type];
        const logger = createLogger(location.name);
        const iterator = indexer(location, logger, databoxClient, options);
        await consume(location, databoxClient, iterator, logger);
    }
}

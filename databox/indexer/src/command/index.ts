import {createDataboxClientFromConfig} from '../databox/client.js';
import {createLogger} from '../lib/logger.js';
import {indexers} from '../indexers.js';
import {getLocation} from '../locations.js';
import {consume} from '../databox/entrypoint.js';
import {runServer} from '../server';
import {CommandCommonOptions} from '../types';
import {applyCommonOptions} from './commandUtil';

export type IndexOptions = {
    createNewWorkspace?: boolean;
} & CommandCommonOptions;

export default async function indexCommand(
    locationName: string,
    options: IndexOptions
) {
    applyCommonOptions(options);
    const location = getLocation(locationName);

    const databoxLogger = createLogger('databox');
    const databoxClient = createDataboxClientFromConfig(databoxLogger);
    const mainLogger = createLogger('app');
    mainLogger.info(`Indexing "${location.name}"...`);

    await databoxClient.authenticate();

    const indexer = indexers[location.type];

    const logger = createLogger(location.name);
    const iterator = indexer(location, logger, databoxClient, options);

    runServer(mainLogger);

    await consume(location, databoxClient, iterator, logger);
}

import {createDataboxClientFromConfig} from '../databox/client.js';
import {createLogger} from '../lib/logger.js';
import {runServer} from "../server";
import {IndexLocation} from "../types/config";
import {getConfig} from "../configLoader";
import {watchers} from "../watchers";

export type WatchOptions = {
};

export default async function watchCommand(options: WatchOptions) {

    const mainLogger = createLogger('app');
    const databoxLogger = createLogger('databox');

    const databoxClient = createDataboxClientFromConfig(databoxLogger);
    databoxClient.authenticate();

    const locations: IndexLocation<any>[] = getConfig('locations');

    locations.forEach(location => {
        mainLogger.debug(`Loading source: ${location.name}`);
        watchers[location.type](
            location,
            databoxClient,
            createLogger(location.name)
        );
    });

    runServer(mainLogger);

}

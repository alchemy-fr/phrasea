import {createDataboxClientFromConfig} from "./databox/client";
import './server';
import {getConfig} from "./configLoader";
import {IndexLocation} from "./types/config";
import {watchers} from "./watchers";
import {createLogger} from "./lib/logger";
import {runServer} from "./server";

const mainLogger = createLogger('app');
const databoxLogger = createLogger('databox');

const databoxClient = createDataboxClientFromConfig(databoxLogger);
databoxClient.authenticate();

const locations: IndexLocation<any>[] = getConfig('locations');

locations.forEach((location) => {
    mainLogger.debug(`Loading source: ${location.name}`);
    watchers[location.type](location, databoxClient, createLogger(location.name));
});

runServer(mainLogger);

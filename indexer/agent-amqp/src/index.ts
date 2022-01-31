import {DataboxClient} from "./lib/databox/client";
import './server';
import {getConfig, getStrict} from "./configLoader";
import {IndexLocation} from "./types/config";
import {handlers} from "./handlers";

console.log('Starting AMQP agent...');

const databoxClient = new DataboxClient({
    apiUrl: getStrict('databox.url'),
    clientId: getStrict('databox.clientId'),
    clientSecret: getStrict('databox.clientSecret'),
    workspaceId: getStrict('databox.workspaceId'),
    collectionId: getStrict('databox.clientSecret'),
    ownerId: getStrict('databox.ownerId'),
    verifySSL: getConfig('databox.verifySSL', true),
    scope: 'chuck-norris'
});

databoxClient.authenticate();

const locations: IndexLocation[] = getConfig('locations');

locations.forEach((location) => {
    console.debug(`Loading source: ${location.name}`);
    handlers[location.type](location, databoxClient);
});

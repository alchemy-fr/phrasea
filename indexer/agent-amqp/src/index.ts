import {listenToQueue} from "./amqp";
import {castEnvToBoolean, getEnv, getEnvStrict} from "./env";
import {DataboxClient} from "./databox/client";
import {handleEvent} from "./listener/eventHandler";

console.log('Starting AMQP agent...');

const dsn = getEnvStrict('AMQP_DSN');

const databoxClient = new DataboxClient({
    apiUrl: getEnvStrict('DATABOX_API_URL'),
    clientId: getEnvStrict('DATABOX_CLIENT_ID'),
    clientSecret: getEnvStrict('DATABOX_CLIENT_SECRET'),
    workspaceId: getEnvStrict('DATABOX_WORKSPACE_ID'),
    collectionId: getEnv('DATABOX_COLLECTION_ID'),
    ownerId: getEnvStrict('DATABOX_OWNER_ID'),
    verifySSL: castEnvToBoolean(getEnv('DATABOX_VERIFY_SSL')),
    scope: 'chuck-norris'
});

databoxClient.authenticate();

listenToQueue(
    dsn,
    's3events',
    async (event) => await handleEvent(event, databoxClient),
    () => {
        console.log('OK, running!');
        console.log('Waiting for events...');
    }
);

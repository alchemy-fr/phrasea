import {configureClientAuthentication, KeycloakClient} from '@alchemy/auth';
import {createHttpClient} from '@alchemy/api';
import config from '../config';

export const keycloakClient = new KeycloakClient({
    clientId: config.clientId,
    baseUrl: config.keycloakUrl,
    realm: config.realmName,
});

export const oauthClient = keycloakClient.client;

const apiClient = createHttpClient(window.config.baseUrl);

configureClientAuthentication(apiClient, oauthClient);

export default apiClient;

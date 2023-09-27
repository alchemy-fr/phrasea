import {configureClientAuthentication, createHttpClient, KeycloakClient} from 'react-ps';
import config from "./config";

export const oauthClient = new KeycloakClient({
    clientId: config.clientId,
    baseUrl: config.keycloakUrl,
    realm: config.realmName,
});

const apiClient = createHttpClient(window.config.baseUrl);

configureClientAuthentication(apiClient, oauthClient);

export default apiClient;

import {configureClientAuthentication, createHttpClient, OAuthClient} from 'react-ps';
import config from "../config";

export const oauthClient = new OAuthClient({
    clientId: config.clientId,
    baseUrl: config.authBaseUrl,
});

const apiClient = createHttpClient(window.config.baseUrl);

configureClientAuthentication(apiClient, oauthClient);

export default apiClient;

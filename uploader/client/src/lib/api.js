import config from '../config';
import {oauthClient} from "../oauth";
import {configureClientAuthentication, createHttpClient} from "@alchemy/auth";

const apiClient = createHttpClient(config.baseUrl);

configureClientAuthentication(apiClient, oauthClient, () => {
    alert('Your session has expired');
});

export default apiClient;

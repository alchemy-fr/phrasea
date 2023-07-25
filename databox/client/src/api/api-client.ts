import {configureClientAuthentication, createHttpClient} from "./http-client";

const apiClient = createHttpClient(window.config.baseUrl);

configureClientAuthentication(apiClient);

export default apiClient;

import {createHttpClient} from "./http-client";

const apiClient = createHttpClient(window.config.baseUrl);

export default apiClient;

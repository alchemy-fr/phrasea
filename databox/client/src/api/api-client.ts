import axios from "axios";

const apiClient = axios.create({
    baseURL: window.config.baseUrl,
});

export default apiClient;

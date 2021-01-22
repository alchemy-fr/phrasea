import axios from "axios";

const apiClient = axios.create({
    baseURL: `${window.config.baseUrl}/api`,
});

// Alter defaults after instance has been created
apiClient.defaults.headers.common['Authorization'] = ``;

export default apiClient;

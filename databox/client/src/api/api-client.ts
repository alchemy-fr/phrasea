import axios from "axios";
import {oauthClient} from "../oauth";

const apiClient = axios.create({
    baseURL: `${window.config.baseUrl}/api`,
});

export default apiClient;
